define(['require', 'jquery', 'theme_ufsm2/jquery.cookie'],
    function (require, $, Cookie) {
        (function () {

            /*
             *  internal
             */

            var _previousResizeWidth = -1,
                _updateTimeout = -1;

            /*
             *  _parse
             *  value parse utility function
             */

            var _parse = function (value) {
                // parse value and convert NaN to 0
                return parseFloat(value) || 0;
            };

            /*
             *  _rows
             *  utility function returns array of jQuery selections representing each row
             *  (as displayed after float wrapping applied by browser)
             */

            var _rows = function (elements) {
                var tolerance = 1,
                    $elements = $(elements),
                    lastTop = null,
                    rows = [];

                // group elements by their top position
                $elements.each(function () {
                    var $that = $(this),
                        top = $that.offset().top - _parse($that.css('margin-top')),
                        lastRow = rows.length > 0 ? rows[rows.length - 1] : null;

                    if (lastRow === null) {
                        // first item on the row, so just push it
                        rows.push($that);
                    } else {
                        // if the row top is the same, add to the row group
                        if (Math.floor(Math.abs(lastTop - top)) <= tolerance) {
                            rows[rows.length - 1] = lastRow.add($that);
                        } else {
                            // otherwise start a new row group
                            rows.push($that);
                        }
                    }

                    // keep track of the last row top
                    lastTop = top;
                });

                return rows;
            };

            /*
             *  _parseOptions
             *  handle plugin options
             */

            var _parseOptions = function (options) {
                var opts = {
                    byRow: true,
                    property: 'height',
                    target: null,
                    remove: false
                };

                if (typeof options === 'object') {
                    return $.extend(opts, options);
                }

                if (typeof options === 'boolean') {
                    opts.byRow = options;
                } else if (options === 'remove') {
                    opts.remove = true;
                }

                return opts;
            };

            /*
             *  matchHeight
             *  plugin definition
             */

            var matchHeight = $.fn.matchHeight = function (options) {
                var opts = _parseOptions(options);

                // handle remove
                if (opts.remove) {
                    var that = this;

                    // remove fixed height from all selected elements
                    this.css(opts.property, '');

                    // remove selected elements from all groups
                    $.each(matchHeight._groups, function (key, group) {
                        group.elements = group.elements.not(that);
                    });

                    // TODO: cleanup empty groups

                    return this;
                }

                if (this.length <= 1 && !opts.target) {
                    return this;
                }

                // keep track of this group so we can re-apply later on load and resize events
                matchHeight._groups.push({
                    elements: this,
                    options: opts
                });

                // match each element's height to the tallest element in the selection
                matchHeight._apply(this, opts);

                return this;
            };

            /*
             *  plugin global options
             */

            matchHeight.version = '0.7.0';
            matchHeight._groups = [];
            matchHeight._throttle = 80;
            matchHeight._maintainScroll = false;
            matchHeight._beforeUpdate = null;
            matchHeight._afterUpdate = null;
            matchHeight._rows = _rows;
            matchHeight._parse = _parse;
            matchHeight._parseOptions = _parseOptions;

            /*
             *  matchHeight._apply
             *  apply matchHeight to given elements
             */

            matchHeight._apply = function (elements, options) {
                var opts = _parseOptions(options),
                    $elements = $(elements),
                    rows = [$elements];

                // take note of scroll position
                var scrollTop = $(window).scrollTop(),
                    htmlHeight = $('html').outerHeight(true);

                // get hidden parents
                var $hiddenParents = $elements.parents().filter(':hidden');

                // cache the original inline style
                $hiddenParents.each(function () {
                    var $that = $(this);
                    $that.data('style-cache', $that.attr('style'));
                });

                // temporarily must force hidden parents visible
                $hiddenParents.css('display', 'block');

                // get rows if using byRow, otherwise assume one row
                if (opts.byRow && !opts.target) {

                    // must first force an arbitrary equal height so floating elements break evenly
                    $elements.each(function () {
                        var $that = $(this),
                            display = $that.css('display');

                        // temporarily force a usable display value
                        if (display !== 'inline-block' && display !== 'flex' && display !== 'inline-flex') {
                            display = 'block';
                        }

                        // cache the original inline style
                        $that.data('style-cache', $that.attr('style'));

                        $that.css({
                            'display': display,
                            'padding-top': '0',
                            'padding-bottom': '0',
                            'margin-top': '0',
                            'margin-bottom': '0',
                            'border-top-width': '0',
                            'border-bottom-width': '0',
                            'height': '100px',
                            'overflow': 'hidden'
                        });
                    });

                    // get the array of rows (based on element top position)
                    rows = _rows($elements);

                    // revert original inline styles
                    $elements.each(function () {
                        var $that = $(this);
                        $that.attr('style', $that.data('style-cache') || '');
                    });
                }

                $.each(rows, function (key, row) {
                    var $row = $(row),
                        targetHeight = 0;

                    if (!opts.target) {
                        // skip apply to rows with only one item
                        if (opts.byRow && $row.length <= 1) {
                            $row.css(opts.property, '');
                            return;
                        }

                        // iterate the row and find the max height
                        $row.each(function () {
                            var $that = $(this),
                                style = $that.attr('style'),
                                display = $that.css('display');

                            // temporarily force a usable display value
                            if (display !== 'inline-block' && display !== 'flex' && display !== 'inline-flex') {
                                display = 'block';
                            }

                            // ensure we get the correct actual height (and not a previously set height value)
                            var css = {'display': display};
                            css[opts.property] = '';
                            $that.css(css);

                            // find the max height (including padding, but not margin)
                            if ($that.outerHeight(false) > targetHeight) {
                                targetHeight = $that.outerHeight(false);
                            }

                            // revert styles
                            if (style) {
                                $that.attr('style', style);
                            } else {
                                $that.css('display', '');
                            }
                        });
                    } else {
                        // if target set, use the height of the target element
                        targetHeight = opts.target.outerHeight(false);
                    }

                    // iterate the row and apply the height to all elements
                    $row.each(function () {
                        var $that = $(this),
                            verticalPadding = 0;

                        // don't apply to a target
                        if (opts.target && $that.is(opts.target)) {
                            return;
                        }

                        // handle padding and border correctly (required when not using border-box)
                        if ($that.css('box-sizing') !== 'border-box') {
                            verticalPadding += _parse($that.css('border-top-width')) + _parse($that.css('border-bottom-width'));
                            verticalPadding += _parse($that.css('padding-top')) + _parse($that.css('padding-bottom'));
                        }

                        // set the height (accounting for padding and border)
                        $that.css(opts.property, (targetHeight - verticalPadding) + 'px');
                    });
                });

                // revert hidden parents
                $hiddenParents.each(function () {
                    var $that = $(this);
                    $that.attr('style', $that.data('style-cache') || null);
                });

                // restore scroll position if enabled
                if (matchHeight._maintainScroll) {
                    $(window).scrollTop((scrollTop / htmlHeight) * $('html').outerHeight(true));
                }

                return this;
            };

            /*
             *  matchHeight._applyDataApi
             *  applies matchHeight to all elements with a data-match-height attribute
             */

            matchHeight._applyDataApi = function () {
                var groups = {};

                // generate groups by their groupId set by elements using data-match-height
                $('[data-match-height], [data-mh]').each(function () {
                    var $this = $(this),
                        groupId = $this.attr('data-mh') || $this.attr('data-match-height');

                    if (groupId in groups) {
                        groups[groupId] = groups[groupId].add($this);
                    } else {
                        groups[groupId] = $this;
                    }
                });

                // apply matchHeight to each group
                $.each(groups, function () {
                    this.matchHeight(true);
                });
            };

            /*
             *  matchHeight._update
             *  updates matchHeight on all current groups with their correct options
             */

            var _update = function (event) {
                if (matchHeight._beforeUpdate) {
                    matchHeight._beforeUpdate(event, matchHeight._groups);
                }

                $.each(matchHeight._groups, function () {
                    matchHeight._apply(this.elements, this.options);
                });

                if (matchHeight._afterUpdate) {
                    matchHeight._afterUpdate(event, matchHeight._groups);
                }
            };

            matchHeight._update = function (throttle, event) {
                // prevent update if fired from a resize event
                // where the viewport width hasn't actually changed
                // fixes an event looping bug in IE8
                if (event && event.type === 'resize') {
                    var windowWidth = $(window).width();
                    if (windowWidth === _previousResizeWidth) {
                        return;
                    }
                    _previousResizeWidth = windowWidth;
                }

                // throttle updates
                if (!throttle) {
                    _update(event);
                } else if (_updateTimeout === -1) {
                    _updateTimeout = setTimeout(function () {
                        _update(event);
                        _updateTimeout = -1;
                    }, matchHeight._throttle);
                }
            };

            /*
             *  bind events
             */

            // apply on DOM ready event
            $(matchHeight._applyDataApi);

            // update heights on load and resize events
            $(window).bind('load', function (event) {
                matchHeight._update(false, event);
            });

            // throttled update heights on resize events
            $(window).bind('resize orientationchange', function (event) {
                matchHeight._update(true, event);
            });

        }(jQuery));
        return {
            init: function (parametros) {
                $('.btn-views a[href$="' + getParameterByName('v') + '"]')
                    .addClass('active')
                    .siblings()
                    .removeClass('active');

                function getParameterByName(name, url) {
                    if (!url) {
                        url = window.location.href;
                    }
                    name = name.replace(/[\[\]]/g, "\\$&");
                    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
                        results = regex.exec(url);

                    if (!results) return null;
                    if (!results[2]) return '';
                    return decodeURIComponent(results[2].replace(/\+/g, " "));
                }


                $('.element-item.mycourses-card').matchHeight();

                var cDisciplina = Cookie.get('filtroDisciplina');
                var cProfessor = Cookie.get('filtroProfessor');
/*
                if (typeof(cDisciplina) != 'undefined' && typeof(cProfessor) != 'undefined') {
                    if ($(".filtro-professor").val() != cProfessor) {
                        $(".filtro-professor").val(cProfessor).change();
                    }
                    if ($(".filtro-disciplina").val() != cDisciplina) {
                        $(".filtro-disciplina").val(cDisciplina).change();
                    }
                }
*/
                var isotope = requirejs(['block_my_course/isotope.pkgd'],
                    function (Isotope) {
                        $('.mycourses-loading').fadeOut(400).addClass('hidden');
                        $('#mycouses-grade').fadeIn(900).removeClass('hidden');
                        $.fn.matchHeight._update();

                        var valorD = $('.filtro-disciplina').val();
                        var valorP = $('.filtro-professor').val();

                        var iso = new Isotope('.grid', {
                            layoutMode: 'fitRows',
                            resizable: true,
                            stagger: 15,
                             filter: function (a) {
                                 if (valorD == '*' && valorP == '*') return true;
                                 if (valorP == '*' && valorD && valorD != '*')
                                     return $(a).find('.categoria').hasClass(valorD);
                                 if (valorD && valorD != '*') {
                                     return $(a).find('.professor').hasClass(valorP) &&
                                         $(a).find('.categoria').hasClass(valorD);
                                 } else
                                     return $(a).find('.professor').hasClass(valorP);
                               /*
                             if ($(".filtro-disciplina").val()) {
                             return $(a).find('.categoria').hasClass($(".filtro-disciplina").val());
                             } else return true;
                             */
                             }
                        });
                        verificarVazio();

                        iso.on('layoutComplete', function (filteredItems) {
                            $.fn.matchHeight._update();
                        });
                        window.iso = iso;

                        $(".filtro-professor").change(function () {
                            var valor2 = $(".filtro-disciplina").val();
                            var valor = this.value;

                            Cookie.set('filtroDisciplina', valor2, {expires: 0.1});
                            Cookie.set('filtroProfessor', valor, {expires:   0.1});
                            iso.arrange({
                                filter: function (a) {
                                    if (valor2 == '*' && valor == '*') return true;
                                    if (valor == '*' && valor2 && valor2 != '*')
                                        return $(a).find('.categoria').hasClass(valor2);
                                    if (valor2 && valor2 != '*') {
                                        return $(a).find('.professor').hasClass(valor) &&
                                            $(a).find('.categoria').hasClass(valor2);
                                    } else
                                        return $(a).find('.professor').hasClass(valor);
                                }
                            });
                            verificarVazio();
                        });

                        $(".filtro-disciplina").change(function () {
                            var valor2 = $(".filtro-professor").val();
                            var valor = this.value;
                            if (valor2 == '') {
                                valor2 = '*';
                            }
                            Cookie.set('filtroDisciplina', valor, {expires: 0.1});
                            Cookie.set('filtroProfessor', valor2, {expires: 0.1});

                            iso.arrange({
                                filter: function (a) {
                                    if (valor2 == '*' && valor == '*') return true;
                                    if (valor == '*' && valor2 && valor2 != '*')
                                        return $(a).find('.professor').hasClass(valor2);
                                    if (valor2 && valor2 != '*') {
                                        return $(a).find('.categoria').hasClass(valor) &&
                                            $(a).find('.professor').hasClass(valor2);
                                    } else
                                        return $(a).find('.categoria').hasClass(valor);
                                }
                            });
                            verificarVazio();
                        });
                        $('.block_my_courses.block.hidden, .block_my_courses .block-hider-show').click(function () {
                            iso.arrange();
                        });

                        function verificarVazio() {
                            if (iso.filteredItems.length == 1 && $(iso.filteredItems[0].element).hasClass('mycourses-title')) {
                                iso.arrange({
                                    filter: function () {
                                        return false;
                                    }
                                });
                                //iso.filteredItems.pop(iso.filteredItems[0]);
                                return $('#na-disciplina').slideDown(100);
                            } else if (iso.filteredItems.length > 1) {
                                return $('#na-disciplina').slideUp(100);
                            } else {
                                return $('#na-disciplina').slideDown(100);
                            }
                        }
                    });
            }
        }
    });
