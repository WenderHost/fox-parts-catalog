<?php
if( ! class_exists( 'WP_CLI' ) )
  return;

/**
 * Interact with the Fox Parts catalog
 */
class Fox_Parts_CLI extends WP_CLI_Command{
    var $imported = 0;

    /**
     * Imports a CSV of parts
     *
     * ## OPTIONS
     *
     * <file>
     * : Full path to the CSV file we're importing
     *
     * In your CSV, setup separate product type groupings by
     * including multiple header rows. Use a new header row
     * for each part type.
     *
     * Example:
     *
     * part_type,size,from_frequency,to_frequency,tolerance,stability,optemp
     * K,122,0.032768,0.032768,E,I,M
     * K,122,0.032768,0.032768,H,I,M
     * K,12A,0.032768,0.032768,E,I,I
     * K,135,0.032768,0.032768,E,I,M
     * K,135,0.032768,0.032768,H,I,M
     * part_type,size,package_option,from_frequency,to_frequency,tolerance,stability,optemp
     * C,4,ST,3.2,80,B,B,D
     * C,4,ST,3.2,80,C,B,D
     * C,4,ST,3.2,80,D,B,D
     * C,4,ST,3.2,80,E,B,D
     * C,4,ST,3.2,80,F,B,D
     *
     * [--delete]
     * : Delete existing parts of the type we're importing
     *
     * ## EXAMPLES
     *
     *  wp foxparts import --file=parts.csv --part_type=crystal
     *
     *  @when typerocket_loaded
     */
    function import( $args, $assoc_args ){
        $file = $args[0];

        if( empty( $file ) )
            WP_CLI::error( 'No --file provided.' );

        if( ! file_exists( $file ) )
            WP_CLI::error( "File `${file}` not found!" );

        if( ( $handle = fopen( $file, 'r' ) ) == FALSE )
            WP_CLI::error( 'Unable to open your CSV file.' );

        // Are we deleting existing parts?
        $delete = ( array_key_exists( 'delete', $assoc_args ) )? true : false;

        $row = 1;
        $table_rows = [];
        $current_part_type_slug = '';
        while( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE  ){
            if( 'part_type' == $data[0] ){
                $columns = $data;
                $num_columns = count( $columns );
                $row++;
                continue;
            }

            $table_row = [];
            for ( $i = 0; $i < count( $data ); $i++ ) {
                $key = $columns[$i];
                $table_row[$key] = $data[$i];
            }
            $part_type_slug = $this->get_part_type_slug( $table_row['part_type'] );

            $part_name = $this->get_part_name( $part_type_slug, $table_row );
            $table_row['name'] = $part_name;
            $table_row['part_type_slug'] = $part_type_slug;

            if( $delete && $current_part_type_slug != $part_type_slug ){
              $this->delete_parts_by_type( $part_type_slug );
              $current_part_type_slug = $part_type_slug;
            }

            $this->create_part( $table_row );

            $table_rows[$row] = $table_row;
            $row++;
        }
        fclose( $handle );
        if( 0 < $this->imported ){
          WP_CLI::success( $this->imported . ' parts imported.' );
        }
    }

    /**
     * Creates a part.
     *
     * @param      array    $part_array  The part array
     *
     * @return     int  Post ID of the new part.
     */
    private function create_part( $part_array ){
      $part_exists = get_page_by_title( $part_array['name'], OBJECT, 'foxpart' );
      if( ! is_null( $part_exists ) ){
        WP_CLI::line('SKIPPING: Part `' . $part_array['name'] . '` exists.');
        return;
      }

      $post_id = wp_insert_post([
        'post_title' => $part_array['name'],
        'post_type' => 'foxpart',
        'post_status' => 'publish'
      ]);

      $add_terms = wp_set_object_terms( $post_id, $part_array['part_type_slug'], 'part_type' );
      if( is_wp_error( $add_terms ) )
        WP_CLI::error( 'Error assigning `part_type`. (' . $add_terms->get_error_message() . ').' );

      $attributes = [];
      switch ( $part_array['part_type_slug'] ) {
        case 'crystal':
          $attributes = ['from_frequency','to_frequency','size','package_option','tolerance','stability','optemp'];
          break;

        case 'crystal-khz':
          $attributes = ['from_frequency','to_frequency','size','tolerance','stability','optemp'];
          break;

        case 'oscillator':
          $attributes = ['from_frequency','to_frequency','size','output','voltage','stability','optemp'];
          break;

        case 'tcxo':
          $attributes = ['from_frequency','to_frequency','pin_1','size','output','voltage','stability','optemp'];
          break;

        case 'vcxo':
          $attributes = ['from_frequency','to_frequency','size','output','voltage','stability','optemp'];
          break;
      }

      if( 0 < count( $attributes ) ){
        foreach ($attributes as $key) {
          if( array_key_exists( $key, $part_array ) )
            add_post_meta( $post_id, $key, $part_array[$key] );
        }
      }

      if( $post_id ){
        WP_CLI::success('Created `' . $part_array['name'] . '`');
        $this->imported++;
      }
      return $post_id;
    }

    /**
     * Deletes all parts for a given `part_type` slug
     *
     * @param      <string>  $part_type   The part_type slug
     */
    private function delete_parts_by_type( $part_type_slug = null ){
      if( is_null( $part_type_slug ) )
        return false;

      // Delete all CPTs of the part_type
      WP_CLI::line( 'Deleting all Fox Parts of of part_type `' . $part_type_slug . '`.' );
      $term = get_term_by( 'slug', $part_type_slug, 'part_type', ARRAY_A );
      if( $term ){
        $term_id = intval( $term['term_id'] );
        global $wpdb;
        $wpdb->query(
          $wpdb->prepare( 'DELETE a,b,c
            FROM ' . $wpdb->prefix . 'posts a
            LEFT JOIN ' . $wpdb->prefix . 'term_relationships b ON ( a.ID = b.object_id )
            LEFT JOIN ' . $wpdb->prefix . 'postmeta c ON ( a.ID = c.post_id )
            LEFT JOIN ' . $wpdb->prefix . 'term_taxonomy d ON ( d.term_taxonomy_id = b.term_taxonomy_id )
            LEFT JOIN ' . $wpdb->prefix . 'terms e ON ( e.term_id = d.term_id )
            WHERE e.term_id=%d', $term_id )
          );

        WP_CLI::success('All `' . $part_type_slug . '` parts were deleted.');
        return true;
      }
      return false;
    }

    /**
     * Gets the part name.
     *
     * @param      string $part_type_slug   The part type
     * @param      array  $part_array  The part array
     *
     * @return     boolean|string  The part name.
     */
    private function get_part_name( $part_type_slug, $part_array ){

      $name = false;

      switch( $part_type_slug ){
        case 'crystal':
          $name_components = ['F','part_type','size','package_option','tolerance','stability','_','optemp'];
          break;

        case 'crystal-khz':
          $name_components = ['F','part_type','size','tolerance','stability','optemp'];
          break;

        case 'oscillator':
          $name_components = ['F','part_type','size','output','voltage','stability','optemp'];
          break;

        case 'tcxo':
          $name_components = ['F','part_type','size','output','pin_1','voltage','stability','optemp'];
          break;

        case 'vcxo':
          $name_components = ['F','part_type','size','output','voltage','stability','optemp'];
          break;

        default:
          $name_components = [];
          break;
      }

      if( ! count( $name_components ) )
        return false;

      foreach ( $name_components as $name_component ) {
        if( 1 === strlen( $name_component ) ){
          $name.= $name_component;
        } else {
          if( isset( $part_array[$name_component] ) )
            $name.= $part_array[$name_component];
        }
      }
      return $name;
    }

    /**
     * Returns the part type slug.
     *
     * @param      <string>  $part_type  The part type code
     *
     * @return     <string>  The part type slug.
     */
    private function get_part_type_slug( $part_type ){
      $part_types_array = FOXPC_PART_TYPES;

      return $part_types_array[$part_type];
    }
}

WP_CLI::add_command( 'foxparts', 'Fox_Parts_CLI' );