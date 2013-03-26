<?php
/**
 * This file contains the Base Class that is to be extended by your child class
 * to regsiter a Custom Post Type, Custom Taxonomy, and Custom Meta Fields.
 */
abstract class zMCustomPostTypeBase {

    public $meta_section = array();
    public $post_type;
    public $meta_keys = array();
    public $asset_url;

    public function __construct() {

        add_filter( 'post_class', array( &$this, 'addPostClass' ) );
        add_action( 'init', array( &$this, 'abstractInit' ) );
        add_action( 'admin_init', array( &$this, 'adminInit' ) );
        add_action( 'wp_head', array( &$this, 'baseAjaxUrl' ) );

        if ( is_admin() ){
            add_action( 'add_meta_boxes', array( &$this, 'metaSection' ) );
            add_action( 'save_post', array( &$this, 'metaSave' ) );
        }
    }


    /**
     * Run the following methods when this Class is called
     */
    public function abstractInit(){
        $this->registerPostType();
        $this->registerTaxonomy();
        $this->enqueueScripts();
    }

    public function adminInit(){

        $dependencies[] = 'jquery';
        $my_plugins_url = $this->asset_url;

        foreach( $this->post_type as $post ){
            if ( ! empty( $_GET['post_type'] ) && $_GET['post_type'] == $post['type'] ){
                wp_enqueue_script( "zm-ev-{$post['type']}-admin-script", $my_plugins_url . $post['type'] . '_admin.js', $dependencies  );
                wp_enqueue_style(  "zm-ev-{$post['type']}-admin-style", $my_plugins_url . $post['type'] . '_admin.css' );
            }
        }
    }


    /**
     * Regsiter an unlimited number of CPTs based on an array of parmas.
     *
     * @uses register_post_type()
     * @uses wp_die()
     * @todo Currently NOT ALL the args are mapped to this method
     * @todo Support Capabilities
     */
    public function registerPostType( $args=NULL ) {

        $taxonomies = $supports = array();

        // our white list taken from http://codex.wordpress.org/Function_Reference/register_post_type see 'supports'
        $white_list = array();

        // Default, title, editor
        $white_list['supports'] = array(
                'title',
                'editor',
                'author',
                'thumbnail',
                'excerpt',
                'comments',
                'custom-fields',
                'trackbacks',
                'revisions'
                );

        foreach ( $this->post_type as $post_type ) {

            if ( !empty( $post_type['taxonomies'] ) )
                $taxonomies = $post_type['taxonomies'];

            $post_type['type'] = strtolower( $post_type['type'] );

            if ( empty( $post_type['singular_name'] ) )
                $post_type['singular_name'] = $post_type['name'];

            // @todo white list rewrite array
            if ( !is_array( $post_type['rewrite'] ) ) {
                $rewrite = true;
            } else {
                $rewrite = $post_type['rewrite'];
            }

            if ( empty( $post_type['menu_name'] ) )
                $post_type['menu_name'] = $post_type['name'];

            if ( ! isset( $post_type['show_ui'] ) )
                $post_type['show_ui'] = true;

            if ( ! isset( $post_type['show_in_menu'] ) )
                $post_type['show_in_menu'] = true;

            if ( ! isset( $post_type['show_in_admin_bar'] ) )
                $post_type['show_in_admin_bar'] = true;

            if ( ! isset( $post_type['show_ui_nav_menu'] ) )
                $post_type['show_ui_nav_menu'] = true;

        $labels = array(
                'name' => _x( $post_type['name'], 'post type general name'),
                'singular_name' => _x( $post_type['singular_name'], 'post type singular name'),
                'add_new' => _x('Add New ' . $post_type['singular_name'] . '', 'something'),
                'menu_name' => _x( $post_type['menu_name'], 'menu name' ),
                'add_new_item' => __('Add New ' . $post_type['singular_name'] . ''),
                'edit_item' => __('Edit '. $post_type['singular_name'] .''),
                'new_item' => __('New '. $post_type['singular_name'] .''),
                'view_item' => __('View '. $post_type['singular_name'] . ''),
                'search_items' => __('Search ' . $post_type['singular_name'] . ''),
                'not_found' => __('No ' . $post_type['singular_name'] . ' found'),
                'not_found_in_trash' => __('No ' . $post_type['singular_name'] . ' found in Trash'),
                'parent_item_colon' => ''
                );

            foreach ( $post_type['supports'] as $temp ) {
                if ( in_array( $temp, $white_list['supports'] ) ) {
                    array_push( $supports, $temp );
                } else {
                    wp_die('gtfo with this sh!t: <b>' . $temp . '</b> it ain\'t in my white list mofo!' );
                }
            }

            $args = array(
                'labels' => $labels,
                'public' => true,
                'supports' => $supports,
                'rewrite' => $rewrite,
                'hierarchical' => true,
                'description' => 'None for now GFYS',
                'taxonomies' => $taxonomies,
                'public' => true,
                'show_ui' => $post_type['show_ui'],
                // 'show_in_menu' => $show_in_menu,
                // 'show_in_admin_bar' => $show_in_admin_bar,
                // 'show_in_nav_menus' => $show_ui_nav_menu,
                'menu_position' => 5,
                'publicly_queryable' => true,
                'exclude_from_search' => false,
                'has_archive' => true,
                'query_var' => true,
                'can_export' => true
                );

            register_post_type( $post_type['type'], $args);

        } // End 'foreach'

        return $this->post_type;
    } // End 'function'


    /**
     * Wrapper for register_taxonomy() to register an unlimited
     * number of taxonomies for a given CPT.
     *
     * @uses register_taxonomy
     *
     * @todo re-map more stuff, current NOT ALL the args are params
     * @todo this 'hierarchical' => false' fucks up on wp_set_post_terms() for submitting and updating a cpt
     */
    public function registerTaxonomy( $args=NULL ) {

        if ( empty( $this->taxonomy ) )
            return;

        foreach ( $this->taxonomy as $taxonomy ) {

            if ( empty( $taxonomy['taxonomy'] ) )
                $taxonomy['taxonomy'] = strtolower( str_replace( " ", "-", $taxonomy['name'] ) );

            if ( empty( $taxonomy['slug'] ) )
                $taxonomy['slug'] = $taxonomy['taxonomy'];

            if ( empty( $taxonomy['singular_name'] ) )
                $taxonomy['singular_name'] = $taxonomy['name'];

            if ( empty( $taxonomy['plural_name'] ) )
                $taxonomy['plural_name'] = $taxonomy['name'] . 's';

            if ( !isset( $taxonomy['hierarchical'] ) ) {
                $taxonomy['hierarchical'] = true;
            }

            if ( empty( $taxonomy['menu_name'] ) ) {
                $taxonomy['menu_name'] = ucfirst( $taxonomy['name'] );
            }

            $labels = array(
                'name'              => _x( $taxonomy['name'], 'taxonomy general name' ),
                'singular_name'     => _x( $taxonomy['singular_name'], 'taxonomy singular name' ),
                'search_items'      => __( 'Search ' . $taxonomy['plural_name'] . ''),
                'all_items'         => __( 'All ' . $taxonomy['plural_name'] . '' ),
                'parent_item'       => __( 'Parent ' . $taxonomy['singular_name'] . '' ),
                'parent_item_colon' => __( 'Parent ' . $taxonomy['singular_name'] . ': ' ),
                'edit_item'         => __( 'Edit ' . $taxonomy['singular_name'] . '' ),
                'update_item'       => __( 'Update ' . $taxonomy['singular_name'] . ''),
                'add_new_item'      => __( 'Add New ' . $taxonomy['singular_name'] . ''),
                'new_item_name'     => __( 'New ' . $taxonomy['singular_name'] . ' Name' ),
                'menu_name'         => __( $taxonomy['menu_name'] )
                );

            $args = array(
                'labels'  => $labels,
                'hierarchical' => $taxonomy['hierarchical'],
                'query_var' => true,
                'public' => true,
                'rewrite' => array('slug' => $taxonomy['slug']),
                'show_in_nav_menus' => true,
                'show_ui' => true,
                'show_tagcloud' => true
                );

            register_taxonomy( $taxonomy['taxonomy'], $taxonomy['post_type'], $args );
        } // End 'foreach'

        return $this->taxonomy;
    } // End 'function'


    /**
     * Auto enqueue Admin and front end CSS and JS files. Based ont the post type.
     * @note CSS and JS files MUST be located in the following location:
     * wp-content/{$my-plugin}/assets/{$my_post_type}.css
     * wp-content/{$my-plugin}/assets/{$my_post_type}_admin.css
     * wp-content/{$my-plugin}/assets/{$my_post_type}.js
     * wp-content/{$my-plugin}/assets/{$my_post_type}_admin.js
     */
    public function enqueueScripts(){

        if ( is_admin() ) return;

        $dependencies[] = 'jquery';
        $my_plugins_url = $this->asset_url;

        foreach( $this->post_type as $post ){
            wp_enqueue_script( "zm-ev-{$post['type']}-script", $my_plugins_url . $post['type'] . '.js', $dependencies  );
            wp_enqueue_style(  "zm-ev-{$post['type']}-style", $my_plugins_url . $post['type'] .  '.css' );
        }
    }

    /**
     * Delets a post given the post ID, post will be moved to the trash
     *
     * @package Ajax
     * @param (int) post id
     * @uses is_wp_error
     * @uses is_user_logged_in
     * @uses wp_trash_post
     *
     * @todo generic validateUser method, check ajax refer and if user can (?)
     */
    public function postTypeDelete( $id=null ) {

        // check_ajax_referer( 'bmx-re-ajax-forms', 'security' );

        $id = (int)$_POST['post_id'];

        if ( !is_user_logged_in() )
            return false;

        if ( is_null( $id )  ) {
            wp_die( 'I need a post_id to kill!');
        } else {
            $result = wp_trash_post( $id );
            if ( is_wp_error( $result ) ) {
                print_r( $result );
            } else {
                print_r( $result );
            }
        }

        die();
    } // postTypeDelete


    /**
     * Print our ajax url in the footer
     *
     * @uses plugin_dir_url()
     * @uses admin_url()
     *
     * @todo baseAjaxUrl() consider moving to abstract
     * @todo consider using localize script
     */
    public function baseAjaxUrl() {
        print '<script type="text/javascript"> var ajaxurl = "'. admin_url("admin-ajax.php") .'";</script>';
    } // End 'baseAjaxUrl'


    /**
     * Adds additional classes to post_class() for additional CSS styling and JavaScript manipulation.
     * term_slug-taxonomy_id
     *
     * @param classes
     *
     * @uses get_post_types()
     * @uses get_the_terms()
     * @uses is_wp_error()
     */
    public function addPostClass( $classes ) {
        global $post;
        $cpt = $post->post_type;

        $cpt_obj = get_post_types( array( 'name' => $cpt ), 'objects' );

        foreach( $cpt_obj[ $cpt ]->taxonomies  as $name ) {
            $terms = get_the_terms( $post->ID, $name );
            if ( !is_wp_error( $terms ) && !empty( $terms )) {
                foreach( $terms as $term ) {
                    $classes[] = $name . '-' . $term->term_id;
                }
            }
        }
        return $classes;
    } // End 'addPostClass'


    /**
     * Basically this is a wrapper for 'add_meta_box'. Allowing
     * us to register an unlimited number of meta sections in an
     * array format.
     *
     * @uses add_meta_box();
     *
     * @note A meta section IS bound by the CLASS NAME!
     * i.e., class name == post_type! PERIOD!
     */
    public function metaSection( $post_type=null ){
        global $post_type;

        // Enusre this ONLY gets ran on $post_type pages
        if ( $post_type != strtolower( get_called_class() ) )
            return;

        if ( ! empty( $this->meta_sections ) ){

            $context_default = array( 'normal', 'advanced', 'side' );

            foreach( $this->meta_sections as $section_id => $section ){
                if ( ! empty( $section['context'] ) && in_array( $section['context'], $context_default ) ){
                    $context = $section['context'];
                } else {
                    $context = 'normal';
                }
                add_meta_box( $section_id, $section['label'], array( &$this, 'metaSectionRender' ), $post_type, $context, $priority='default', $section['fields'] );
            }
        }
    }


    /**
     * Renders the HTML for each 'meta section'
     *
     * @note The unique form field name, i.e. "key", is derived by the following
     * {$post_type}_{$label=converted to lower case, and replace spaces with a dash}
     * i.e., $label="First Name", $post_type="events", $key=events_first-name
     * @todo add checkbox, radio, and select type support.
     * @note you can override the field name
     */
    public function metaSectionRender( $post, $args ){
        foreach( $args['args'] as $field ){
            $name = null;
            if ( empty( $field['label'] ) ){
                $label = null;
            } else {
                if ( empty( $field['name'] ) )
                    $name = $post->post_type . '_' . str_replace(' ', '-', strtolower( $field['label'] ) );
                else
                    $name = $field['name'];

                $label = $field['label'];
            }

            if ( ! empty( $field['value'] ) ){
                $tmp_value = $field['value'];
            } else if ( get_post_meta( $post->ID, "{$name}", true) ){
                $tmp_value = get_post_meta( $post->ID, "{$name}", true );
            } else {
                $tmp_value = null;
            }

            empty( $field['class'] ) ? $class = null : $class = $field['class'];
            empty( $field['placeholder'] ) ? $placeholder = null : $placeholder = $field['placeholder'];

            switch( $field['type'] ){
                case 'text': // type="text"
                    print "<p><label>{$label}</label><input type='{$field['type']}' class='{$class}' name='{$name}' value='{$tmp_value}' placeholder='{$placeholder}'/></p>";
                    break;
                case 'description': // If using a function make sure it returns!
                case 'html': // Just add your stuff in the "value => 'anything you want"
                    print "$tmp_value";
                    break;
                default:
                    print 'This is the default type';
                    break;
            }
        }
    }


    /**
     * Build our unique keys for each meta section
     */
    public function buildMetaKeys(){
        global $post;

        foreach( $this->meta_sections as $section_id => $section ){
            foreach( $section['fields'] as $field ){
                if ( empty( $field['name'] ) )
                    $this->meta_keys[] = $post->post_type . '_' . str_replace(' ', '-', strtolower( $field['label'] ) );
                else
                    $this->meta_keys[] = $field['name'];
            }
        }
        return $this->meta_keys;
    }


    /**
     * Saves post meta information based on $_POST['post_id']
     * @todo Add support for $post_id
     * @todo Use a nonce?
     */
    public function metaSave( $post_id=null ){

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;

        if ( empty( $_POST['post_ID'] ) )
            return;

        /**
         * This is done to derive our meta keys, since wp doesn't scale well from a code
         * point of view. There's no direct access to post meta keys that don't already exists in
         * the db. So post meta keys are derived like {$post_type}_{name}.
         */
        $post_type = $_POST['post_type'];
        $new_meta = array();
        foreach( $_POST as $field => $value ){
            if ( ! is_array( $field ) ){
                $tmp = explode( '_', $field );
                if ( $tmp[0] == $post_type ){
                    $new_meta[$field] = $value;
                }
            }
        }

        $current_meta = get_post_custom( $_POST['post_ID'] );

        foreach( $new_meta as $key => $value ){
            if ( ! empty( $value ) ) {
                if ( is_array( $value ) ){
                    $value = $value[0]."\n";
                }
                update_post_meta( $_POST['post_ID'], $key, $value );
            }
        }
    }


    /**
     * Attempts to locate the called template from the child or parent theme.
     * If not it loads the one in the plugin.
     *
     * @param $template The file name, "settings.php"
     * @param $views_dir The path to the template/view as seen in the plugin, "views/"
     */
    public function loadTemplate( $template=null, $views_dir=null ){
        $template = ($overridden_template = locate_template( $template )) ? $overridden_template : $views_dir . $template;
        load_template( $template );
    }
} // End 'CustomPostTypeBase'