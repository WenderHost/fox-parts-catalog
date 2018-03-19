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
     * : Full path to the file we're importing
     *
     * [--part_type=<part_type>]
     * : The type of parts we're importing (e.g. crystal). Must match name of existing WordPress post_type.
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
        $part_type = $assoc_args['part_type'];

        if( empty( $part_type ) )
            WP_CLI::error( 'No --part_type provided.' );

        $allowed_part_types = ['crystal','oscillator'];
        if( ! in_array( $part_type, $allowed_part_types ) )
            WP_CLI::error('Invalid `part_type`. Please specify one of the following part types: ' . "\n - " . implode( "\n" . ' - ', $allowed_part_types ) );

        if( empty( $file ) )
            WP_CLI::error( 'No --file provided.' );

        if( ! file_exists( $file ) )
            WP_CLI::error( "File `${file}` not found!" );

        if( ( $handle = fopen( $file, 'r' ) ) == FALSE )
            WP_CLI::error( 'Unable to open your CSV file.' );

        // Are we deleting existing parts?
        $delete = ( array_key_exists( 'delete', $assoc_args ) )? true : false;

        // Delete all CPTs of the part_type
        if( $delete ){
          WP_CLI::line( 'Deleting all Fox Parts of of part_type `' . $part_type . '`.' );
          $term = get_term_by( 'slug', $part_type, 'part_type', ARRAY_A );
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
          }
        }

        $row = 1;
        $table_rows = [];
        while( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE  ){
            if( 1 === $row && 'part_type' == $data[0] ){
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

            $part_name = $this->get_part_name( $part_type, $table_row );
            $table_row['name'] = $part_name;
            $table_row['part_type'] = $part_type;
            $this->create_part( $part_type, $table_row );

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
     * @param      string   $part_type   The part type
     * @param      array    $part_array  The part array
     *
     * @return     int  Post ID of the new part.
     */
    private function create_part( $part_type, $part_array ){
      $post_id = wp_insert_post([
        'post_title' => $part_array['name'],
        'post_type' => 'foxpart',
        'post_status' => 'publish'
      ]);

      $add_terms = wp_set_object_terms( $post_id, $part_array['part_type'], 'part_type' );
      if( is_wp_error( $add_terms ) )
        WP_CLI::error( 'Error assigning `part_type`. (' . $add_terms->get_error_message() . ').' );

      $attributes = [];
      switch ( $part_array['part_type'] ) {
        case 'crystal':
          $attributes = ['from_frequency','to_frequency','size','package_option','tolerance','stability','optemp'];
          break;
        case 'oscillator':
          $attributes = ['from_frequency','to_frequency','size','package_option','voltage','stability','optemp'];
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
     * Gets the part name.
     *
     * @param      string $part_type   The part type
     * @param      array  $part_array  The part array
     *
     * @return     boolean|string  The part name.
     */
    private function get_part_name( $part_type, $part_array ){
      $name = false;
      switch( $part_type ){
        case 'crystal':
          $name = 'F' . $part_array['part_type'] . $part_array['size'] . $part_array['package_option'] . $part_array['tolerance'] . $part_array['stability'] . '_' . $part_array['optemp'];
          break;
        case 'oscillator':
          $name = 'F' . $part_array['part_type'] . $part_array['size'] . $part_array['package_option'] . $part_array['voltage'] . $part_array['stability'] . '_' . $part_array['optemp'];
      }
      return $name;
    }
}

WP_CLI::add_command( 'foxparts', 'Fox_Parts_CLI' );