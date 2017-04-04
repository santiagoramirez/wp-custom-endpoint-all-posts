<?php

/**
 * Custom posts endpoint
 *
 * ADVANTAGES:
 *
 * Allows posts from multiple post types to be pulled using the
 * 'type' argument.
 *
 * Allows posts to be sorted by taxonomies using the 'tax_{name}' argument.
 *
 * Allows posts to be sorted by an event date using the 'event_before'
 * and 'event_after' argument.
 */

class Custom_Endpoint_All_Posts {

    /**
     * @var string $_namespace
     * REST API namespace
     */
    protected $_namespace = 'custom-endpoint/v1';

    /**
     * @var string $_resource_name
     * REST API resource name
     */
    protected $_resource_name = '/all-posts';

    /**
     * Class constructor
     */
    public function __construct() {

        add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );

        add_filter( 'custom_endpoint_arg' . $this->_resource_name, array( $this, 'handle_arg_event_range' ), 10, 2 );
        add_filter( 'custom_endpoint_arg' . $this->_resource_name, array( $this, 'handle_arg_tax_name' ), 10, 2 );

        add_filter( 'custom_endpoint_field' . $this->_resource_name, array( $this, 'get_field_acf' ), 10, 1 );
        add_filter( 'custom_endpoint_field' . $this->_resource_name, array( $this, 'get_field_tax_name' ), 10, 1 );

    }

    /**
     * Register custom endpoints
     */
    public function register_rest_route() {

        register_rest_route( $this->_namespace, $this->_resource_name, array(
            'methods' => 'GET',
            'callback' => array( $this, 'handler' ),
            'args' => array(
                'order' => array(
                    'default' => 'ASC',
                ),
                'orderby' => array(
                    'default' => 'date',
                ),
                'page' => array(
                    'default' => 1,
                ),
                'per_page' => array(
                    'default' => 10,
                ),
                'type' => array(
                    'default' => 'post',
                ),
                'event_after' => array(
                    'default' => false,
                ),
                'event_before' => array(
                    'default' => false,
                ),
            ),
        ) );

    }

    /**
     * Handle REST API request for /all-posts
     * @param object $request
     * @return array $posts
     */
    public function handler( $request ) {

        $args = array(
            'paged' => $request->get_param( 'page' ),
            'showposts' => $request->get_param( 'per_page' ),
            'order' => $request->get_param( 'order' ),
            'orderby' => $request->get_param( 'orderby' ),
            'meta_query' => array(
                'relation' => 'AND',
            ),
        );

        if ( $request->get_param( 'type' ) ) {
            $args['post_type'] = explode( ',', $request->get_param( 'type' ) );
        }

        $args = apply_filters( 'custom_endpoint_arg' . $this->_resource_name, $args, $request );
        $query = new WP_Query( $args );
        $posts = $query->posts;

        for ( $i = 0; $i < count( $posts ); $i++ ) {
            $post = array();
            $post['date'] = $posts[$i]->post_date;
            $post['date_gmt'] = $posts[$i]->post_date_gmt;
            $post['guid'] = $posts[$i]->guid;
            $post['id'] = $posts[$i]->ID;
            $post['link'] = get_permalink( $posts[$i] );
            $post['modified'] = $posts[$i]->post_modified;
            $post['modified_gmt'] = $posts[$i]->post_modified_gmt;
            $post['slug'] = $posts[$i]->post_name;
            $post['status'] = $posts[$i]->post_status;
            $post['type'] = $posts[$i]->post_type;
            $post['title'] = $posts[$i]->post_title;
            $post['content'] = $posts[$i]->post_content;
            $post['author'] = $posts[$i]->post_author;
            $post['excerpt'] = $posts[$i]->post_excerpt;
            $post['comment_status'] = $posts[$i]->comment_status;
            $posts[$i] = apply_filters( 'custom_endpoint_field' . $this->_resource_name, $post );
        }

        header( 'X-WP-Total: ' . $query->post_count );
        header( 'X-WP-TotalPages: ' . $query->max_num_pages );

        return $posts;

    }

    /**
     * Filter posts by taxonomy
     * @param array $args
     * @param object $request
     * @return array $args
     */
    public function handle_arg_tax_name( $args, $request ) {

        $args['tax_query'] = array();
        $taxonomies = get_taxonomies();

        foreach ( $taxonomies as $taxonomy ) {
            if ( $request->get_param( 'tax_' . $taxonomy ) ) {
                $args['tax_query'][] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => explode( ',', $request->get_param( 'tax_' . $taxonomy ) ),
                );
            }
        }

        return $args;

    }

    /**
     * Filter posts by an event range
     * @param array $args
     * @param object $request
     * @return array $args
     */
    public function handle_arg_event_range( $args, $request ) {

        $event_before = $request->get_param( 'event_before' );
        $event_after = $request->get_param( 'event_after' );

        if ( $event_before || $event_after) {
            $meta_query = array(
                'relation' => 'AND',
            );
        }

        if ( $event_after ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => 'date',
                    'value' => $event_after,
                    'type' => 'numeric',
                    'compare' => '>=',
                ),
                array(
                    'key' => 'end_date',
                    'value' => $event_after,
                    'type' => 'numeric',
                    'compare' => '>=',
                ),
            );
        }

        if ( $event_before ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => 'date',
                    'value' => $event_before,
                    'type' => 'numeric',
                    'compare' => '<=',
                ),
            );
        }

        if ( isset( $meta_query ) ) {
            $args['meta_query'][] = $meta_query;
    		$args['meta_key'] = 'date';
            $args['orderby'] = 'meta_value';
        }

        return $args;

    }

    /**
     * Get all ACF fields of a given post
     * @param array $post
     * @return array $post
     */
    public function get_field_acf( $post ) {

    	if ( function_exists( 'get_field_objects' ) ) {
    		$fields = get_field_objects( $post['id'] );
            if ( $fields ) {
        		foreach ( $fields as $key => $field ) {
        			$fields[$key] = $field['value'];
        		}
            }
    		$post['acf'] = $fields;
    	}

    	return $post;

    }

    /**
     * Get list of post taxonomies
     * @param array $post
     * @return array $post
     */
    public function get_field_tax_name( $post ) {

    	$taxonomies = get_post_taxonomies( $post['id'] );
        $ignore = array( 'post_format' );

    	foreach ( $taxonomies as $taxonomy ) {
    		$terms = wp_get_post_terms( $post['id'], $taxonomy );
            if ( $taxonomy == 'post_tag' ) {
                $key = 'tags';
            } else if ( $taxonomy == 'category' ) {
                $key = 'categories';
            } else {
                $key = 'tax_' . $taxonomy;
            }
            if ( !in_array( $taxonomy, $ignore ) ) {
                $post[$key] = $terms;
            }
    	}

    	return $post;

    }

}

new Custom_Endpoint_All_Posts();
