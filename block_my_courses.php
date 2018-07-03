<?php

class block_my_courses extends block_base
{

    public $coursoID = '*';
    public $disciplinaCor = [
        '#091e63',
        '#7b1fa2',
        '#3949ab',
        '#2196f3',
        '#00acc1',
        '#cddc39',
        '#ffc107',
        '#f57c00',
        '#f4511e',
        '#4caf50',
        '#8bc34a',
        '#8bc34a',
        '#b71c1c',
    ];
    public $up = 0;

    function init()
    {
        $this->title = get_string('pluginname', 'block_my_courses');
    }

    function applicable_formats()
    {
        return array('all' => true);
    }

    function has_config()
    {
        return true;
    }

    public function instance_allow_config()
    {
        return true;
    }

    /**
     *
     */
    function specialization()
    {
        $this->title = get_string('pluginname', 'block_my_courses');
    }

    /**
     * Gets Javascript that may be required for navigation
     */
    function get_required_javascript()
    {

        parent::get_required_javascript();
        $this->page->requires->js_call_amd('block_my_courses/courses', 'init', [[$this->coursoID]]);
    }

    /**
     * @return array
     */
    function get_content()
    {
        if (isguestuser() or !isloggedin()) {
            return (array());
        }
        global $DB, $USER;
        $v = optional_param('v', '', PARAM_RAW);
        $this->content = new stdClass();
        $this->content->text = $this->styleSheet();

        switch ($v) {
            case 'l':
                $this->content->text .= $this->viewTree();
                break;
            case 'c':
                $this->content->text .= $this->viewTeacher();
                break;

            default:
                if (isset($this->config->defaultView)) {
                    $this->content->text .= !$this->config->defaultView ?
                        $this->viewTeacher() : $this->viewTree();
                } else
                    $this->content->text .= $this->viewTeacher();
                break;
        }
        return $this->content;
    }

    /**
     *
     * Gera uma barra de filtros
     * @param $opt1
     * @param $opt2
     *
     * @return string
     */
    protected function filterBar($opt1, $opt2, $opt3)
    {
        return html_writer::tag('form',
            html_writer::div(
                html_writer::span(
                    get_string('filterby', 'block_my_courses'), '', ['style' => 'margin-right:10px;']
                ), 'form-group') .
            html_writer::div(
                html_writer::select(
                    $opt2[0], $opt2[1], $opt2[2], ['' => 'choosedots'], [
                        'class' => 'input-select2 filtro-disciplina  form-control',
                        'data-placeholder' => $opt2[1]
                    ]
                ), 'form-group') .
            html_writer::div(
                html_writer::select(
                    $opt1[0], $opt1[1], $opt1[2], ['' => 'choosedots'], [
                        'data-placeholder' => $opt1[1],
                        'class' => 'input-select2 filtro-professor  form-control',
                    ]
                ), 'form-group') .
            $this->btnViws($opt3),
            ['class' => 'row form-inline']
        );
    }


    /**
     * Gera um esquema de loading
     * @return string
     */
    protected function nowLoading()
    {
        return html_writer::div(
            html_writer::tag('i', '', ['class' => 'fa fa-spinner fa-pulse fa-4x']) .
            html_writer::span('Aguarde, carregando os cursos', 'sr-only')
            , 'row mycourses-loading');
    }

    /**
     * Gera uma div informando que não existem cursos disponíveis para o filtro selecionado
     * @return string
     */
    protected function nothingAvalible()
    {
        return html_writer::div(
            html_writer::div(get_string('nothingAvaliableCourse', 'block_my_courses'), 'mycourses-titulo'),
            'alert alert-info', ['role' => 'alert', 'style' => 'display:none;', 'id' => 'na-disciplina']
        );
    }

    /**
     * Gera os botões de navegações entre as views
     * @return string
     */
    protected function btnViws($op = [null, null, null])
    {
        return html_writer::div(
            html_writer::link(new moodle_url('/my', ['v' => 'l']),
                html_writer::tag('i', null, ['class' => 'fa fa-list']),
                [
                    'class' => "btn btn-default btn-sm {$op[0]}",
                    'title' => get_string('btnList', 'block_my_courses'),
                ]) .
            html_writer::link(new moodle_url('/my', ['v' => 'c']),
                html_writer::tag('i', null, ['class' => 'fa fa-th']), [
                    'class' => "btn btn-default btn-sm {$op[1]}",
                    'title' => get_string('btnGroup', 'block_my_courses'),
                ])
            ,
            'btn-views content pull-right btn-group'
        );
    }


    /**
     * @return string
     */
    protected function viewTree()
    {
        $categorias = [];
        foreach ($this->enrol_get_my_courses("id,category,fullname", "category desc, fullname asc") as $t) {
            $categorias[$this->root_category($t, 2)][$this->root_category($t, 1)][] = [$t->fullname, $t->id];
        }
        krsort($categorias);
        $categorias = array_map(function($a){krsort($a); return $a;},$categorias);

        $bloco = html_writer::start_tag('ul', ['class' => 'lista']);
        foreach ($categorias as $chave => $t) {
            $bloco .= html_writer::start_tag('li');
            $bloco .= html_writer::tag('h4', mb_strtoupper($chave, 'UTF-8'));
            if (is_array($t) and count($t) > 0) {
                $bloco .= html_writer::start_tag('ul');
            }
            foreach ($t as $chave2 => $j) {
                $bloco .= html_writer::start_tag('li');
                $bloco .= html_writer::tag('h5', mb_strtoupper($chave2, 'UTF-8'));
                if (is_array($j) and count($j) > 0) {
                    $bloco .= html_writer::start_tag('ul');
                }
                foreach ($j as $r) {
                    $bloco .= html_writer::tag('li',
                        html_writer::link(new moodle_url('/course/view.php', ['id' => $r[1]]),
                            html_writer::tag('i', null, ['class' => 'fa fa-book']) . ' ' . $r[0] . " "
                        )
                    );
                }
                if (is_array($j) and count($j) > 0) {
                    $bloco .= html_writer::end_tag('ul');
                }
            }
            if (is_array($t) and count($t) > 0) {
                $bloco .= html_writer::end_tag('ul');
            }
            html_writer::end_tag('li');
        }
        $bloco .= html_writer::end_tag('ul');


        return html_writer::div($this->btnViws([0 => 'active', 1 => null, 2 => null]), 'row')
            . html_writer::div($bloco, 'mycourses-tree');

    }

    /**
     * @return string
     */
    protected function viewTeacher()
    {
        global $CFG;
        $bloco = '';
        $contato = '';
        $cat = [];
        $categorias = [];
        $registros = [];
        $responsaveis = [];
        $pp = [];
        $cc = [];
        $categoriaPadrao = '';
        foreach ($this->enrol_get_my_courses("id,category,fullname", "fullname asc") as $t) {
            $contatos = $this->coursecat_coursebox_content_contacts($t);
            $professoresClasse = '';
            if (count($contatos) == 0) {
                $contato = "Nenhum professor";
            } elseif (count($contatos) == 1) {
                $contato = $contatos[0][1];
            } elseif (count($contatos) > 1) {
                $contato = "Vários professores";
            }

            foreach ($contatos as $prof) {
                $responsaveis[$prof[1]] = $prof[1];
                $professoresClasse .= " " . $prof[1];
            }

            $rc1 = $this->root_category($t, 2, 1);
            $rc2 = $this->root_category($t, 1, 1);
            $registros[$rc1[0]][$rc2[0]][] = [$t->fullname, $t->id, $contato, $professoresClasse];
            $rootCategoryId[$rc1[0]] = $rc1[1];
            $cat[$rc2[1]] = $rc2[0];
            $pp[$rc1[0]][] = $professoresClasse;
            $cc[$rc1[0]][] = $rc2[0];
        }
        $pp = array_map(function ($a) {
            return implode(" ", $a);
        }, $pp);
        $cc = array_map(function ($a) {
            return implode(" ", $a);
        }, $cc);
        krsort($registros,SORT_NATURAL);
        $registros = array_map(function($a){krsort($a,SORT_NATURAL); return $a;},$registros);

        foreach ($registros as $chave => $t) {
            $bloco .= html_writer::div(
                html_writer::tag('h4', $chave, ['class' => 'professor ' . $pp[$chave]]) .
                html_writer::tag('div', '', ['class' => "categoria " . $cc[$chave]])
                , 'element-item mycourses-title ' . $chave);
            foreach ($t as $chave2 => $j) {
                if (!array_key_exists($chave2, $categorias)) {
                    $categorias[$chave2] = $chave2;
                }
                foreach ($j as $r) {
                    $bloco .= $this->templateCourse($r[0], $r[1], $r[2], $chave2, $chave2, $r[3] . ' ' . $chave);
                }
            }
        }

        asort($responsaveis, SORT_NATURAL);
        asort($categorias, SORT_NATURAL);
        $categorias = array_reverse($categorias, true);

        //Categoria padrão
        if (isset($CFG->block_my_courses_default_category) and $CFG->block_my_courses_default_category != "") {
            if (array_search($CFG->block_my_courses_default_category, $cat)) {
                if (isset($this->config->defaultCategory) and $this->config->defaultCategory != "") {
                    $categoriaPadrao = $this->config->defaultCategory;
                } else {
                    $categoriaPadrao = $CFG->block_my_courses_default_category;
                }

            } else {
                if (isset($this->config->defaultCategory) and $this->config->defaultCategory != "") {
                    $categoriaPadrao = $this->config->defaultCategory;
                } else {
                    $categoriaPadrao = $cat[max(array_keys($cat))];
                }
            }
        } else {
            if (isset($this->config->defaultCategory) and $this->config->defaultCategory != "") {
                $categoriaPadrao = $this->config->defaultCategory;
            } else {
                $categoriaPadrao = $cat[max(array_keys($cat))];
            }
        }

        if (isset($_COOKIE['filtroDisciplina'])) {
            $categoriaPadrao = $_COOKIE['filtroDisciplina'];
        }

        if (isset($_COOKIE['filtroProfessor'])) {
            $responsavelPadrao = $_COOKIE['filtroProfessor'];
        } else {
            $responsavelPadrao = null;
        }

        return html_writer::tag('form',
                $this->filterBar(
                    [["*" => "Todos"] + $responsaveis, get_string('responsable', 'block_my_courses'), $responsavelPadrao],
                    [["*" => "Todos"] + $categorias, get_string('category', 'block_my_courses'), $categoriaPadrao
                    ],
                    [0 => null, 1 => 'active', 2 => null]
                ),
                ['class' => 'row form-inline']
            ) . $this->nowLoading() .
            html_writer::div($bloco, 'row grid hidden', ['id' => "mycouses-grade"]) . $this->nothingAvalible();
    }

    /**
     * Recebe uma string e retorna um valor
     * @return string
     */
    protected function calculaCor()
    {
        $this->up = $this->up >= 11 ? 0 : $this->up + 1;
        return $this->disciplinaCor[$this->up];
    }

    /**
     * Template que gera o card do curso
     * @param $nome
     * @param $id
     * @param $contato
     * @param $categoria
     * @param $classe0
     * @param $classe1
     * @return string
     */
    protected function templateCourse($nome, $id, $contato, $categoria, $classe0, $classe1)
    {
        return html_writer::link(new moodle_url('/course/view.php', ['id' => $id]),
            html_writer::div(
                html_writer::div($categoria, "categoria $classe0") .
                html_writer::div($nome, 'mycourses-titulo') .
                html_writer::div($contato, 'professor ' . $classe1) .
                html_writer::div(get_string('viewcourses', 'block_my_courses'), 'mycourses-viecontent'),
                '',
                ['style' => "border-top: 4px solid {$this->calculaCor()}"]
            ),
            ['class' => 'element-item mycourses-card', 'title' => "$categoria  - $nome"]
        );
    }

    /**
     * Overide Function
     * Returns list of courses current $USER is enrolled in and can access
     * - $fields is an array of field names to ADD
     *   so name the fields you really need, which will
     *   be added and uniq'd
     * @param string|array $fields
     * @param string $sort
     * @param int $limit max number of courses
     * @return array
     * @throws coding_exception
     */
    protected function enrol_get_my_courses($fields = NULL, $sort = 'visible DESC,sortorder ASC', $roleid = 0, $limit = 0)
    {
        global $DB, $USER;

        // Guest account does not have any courses
        if (isguestuser() or !isloggedin()) {
            return (array());
        }

        $basefields = array('id', 'category', 'sortorder',
            'shortname', 'fullname', 'idnumber',
            'startdate', 'visible',
            'groupmode', 'groupmodeforce', 'cacherev');

        if (empty($fields)) {
            $fields = $basefields;
        } else if (is_string($fields)) {
            // turn the fields from a string to an array
            $fields = explode(',', $fields);
            $fields = array_map('trim', $fields);
            $fields = array_unique(array_merge($basefields, $fields));
        } else if (is_array($fields)) {
            $fields = array_unique(array_merge($basefields, $fields));
        } else {
            throw new coding_exception('Invalid $fileds parameter in enrol_get_my_courses()');
        }
        if (in_array('*', $fields)) {
            $fields = array('*');
        }

        $orderby = "";
        $sort = trim($sort);
        if (!empty($sort)) {
            $rawsorts = explode(',', $sort);
            $sorts = array();
            foreach ($rawsorts as $rawsort) {
                $rawsort = trim($rawsort);
                if (strpos($rawsort, 'c.') === 0) {
                    $rawsort = substr($rawsort, 2);
                }
                $sorts[] = trim($rawsort);
            }
            $sort = 'c.' . implode(',c.', $sorts);
            $orderby = "ORDER BY  $sort";
        }

        $wheres = array("c.id <> :siteid");
        $params = array('siteid' => SITEID);


        if (isset($USER->loginascontext) and $USER->loginascontext->contextlevel == CONTEXT_COURSE) {
            // list _only_ this course - anything else is asking for trouble...
            $wheres[] = "courseid = :loginas";
            $params['loginas'] = $USER->loginascontext->instanceid;
        }

        $coursefields = 'c.' . join(',c.', $fields);
        $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel) ";

        if ($roleid) {
            $ccjoin .= "LEFT JOIN {role_assignments} ra on (ra.contextid =ctx.id  and ra.userid = :userid2 ) ";
            $params['userid2'] = $USER->id;
            $wheres[] = "ra.roleid = :roleid2";
            $params['roleid2'] = $roleid;
        }

        $params['contextlevel'] = CONTEXT_COURSE;
        $wheres = implode(" AND ", $wheres);
        $sql = "SELECT $coursefields $ccselect
              FROM {course} c
              JOIN (SELECT DISTINCT e.courseid
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = :userid)
                      
                      WHERE ue.status = :active AND e.status = :enabled AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)
                   ) en ON (en.courseid = c.id)
             
           $ccjoin 
             WHERE $wheres
           $orderby";
        $params['userid'] = $USER->id;


        $params['active'] = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;
        $params['now1'] = round(time(), -2); // improves db caching
        $params['now2'] = $params['now1'];


        $courses = $DB->get_records_sql($sql, $params, 0, $limit);

        // preload contexts and check visibility
        foreach ($courses as $id => $course) {
            context_helper::preload_from_record($course);
            if (!$course->visible) {
                if (!$context = context_course::instance($id, IGNORE_MISSING)) {
                    unset($courses[$id]);
                    continue;
                }
                if (!has_capability('moodle/course:viewhiddencourses', $context)) {
                    unset($courses[$id]);
                    continue;
                }
            }
            $courses[$id] = $course;
        }
        return $courses;
    }


    /**
     * @param $course
     * @return array
     */
    protected function coursecat_coursebox_content_contacts($course)
    {
        global $CFG;
        if ($course instanceof stdClass) {
            require_once($CFG->libdir . '/coursecatlib.php');
            $course = new course_in_list($course);
        }
        $content = [];
        if ($course->has_course_contacts()) {
            foreach ($course->get_course_contacts() as $userid => $coursecontact) {
                $content[] = [$userid, $coursecontact['username']];
            }
        }
        return $content;
    }

    /**
     * @param $course
     * @param int $dep
     * @param int $r
     * @return mixed
     */
    public function root_category($course, $dep = 1, $r = 0)
    {
        $arr = [':', ''];
        global $DB;
        $category = $DB->get_record('course_categories', array('id' => $course->category));
        $path = explode('/', $category->path);
        $root_category_id = (count($path) - $dep > 0) ? $path[count($path) - $dep] : end($path);
        $root_category = $DB->get_record('course_categories', array('id' => $root_category_id));
        $nome = str_replace($arr, "", $root_category->name);
        return !$r ? $nome : [$nome, $root_category_id];
    }


    /**
     * @param $idCategory
     * @return string
     */
    public function children_category($idCategory)
    {
        global $DB;
        $category = $DB->get_records('course_categories', ['parent' => $idCategory]);
        return implode(" ", array_map(function ($a) {
            return $a->name;
        }, $category));
    }


    /**
     * Gera uma folha de estilos
     * @return string
     */
    private function styleSheet()
    {
        return <<<STILO
        <style>
        .mycourses-tree {
            display: block;
            background: #fff;
            padding: 10px;
         }
        .lista, 
        .lista ul,
        .lista ul li{
            margin-left:8px;
            list-style: none;
                /*margin-bottom: 8px;*/
         }
        .mycourses-title{
             display:block;
             width:100%
         }
        .mycourses-card:hover{
            background-color:#fcfcfc !important;
            text-decoration:none;
        }
        .mycourses-card{
            color:#333;
            display:block;
            float:left;
            background-color:#fff !important;
            margin-bottom:8px;
            border-radius:4px;
        }
        .mycourses-card >div{
            padding:12px
        }
        .professor{
            padding-bottom:8px;
        }
        .mycourses-titulo {
            font-weight: bold;
            font-size: 13px;
            margin-bottom:5px;
            margin-top:5px;
        }
        .mycourses-viecontent{
            position: absolute;
            bottom: 2px;
            right: 8px;
            padding-top:8px;
        }
        .mycourses-loading {
            margin-top:15%;
            text-align: center;
            display: block;
            vertical-align: middle;
            min-width: 100%;
        }
        @media screen and (min-width: 994px) {
            .mycourses-card {
                width:49% !important;
            }
            .mycourses-card:not(:last-child) {
                margin-right:1%;
            }
        }
        @media screen and (max-width: 993px) {
            .mycourses-card{
                width:100% !important;
            }
        }
        </style>
STILO;

    }
}