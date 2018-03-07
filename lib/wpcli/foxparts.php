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

        $delete = ( array_key_exists( 'delete', $assoc_args ) )? true : false;

        if( empty( $file ) )
            WP_CLI::error( 'No --file provided.' );

        if( empty( $part_type ) )
            WP_CLI::error( 'No --part_type provided.' );

        if( ! file_exists( $file ) )
            WP_CLI::error( "File `${file}` not found!" );

        if( ! post_type_exists( $part_type ) )
            WP_CLI::error( "No WordPress post_type exists for your specified --part_type ${part_type}." );

        if( ( $handle = fopen( $file, 'r' ) ) == FALSE )
            WP_CLI::error( 'Unable to open your CSV file.' );

        // Delete all CPTs of the part_type
        if( $delete ){
          WP_CLI::line( 'Deleting all posts of type `' . $part_type . '`.' );
          global $wpdb;
          $wpdb->query(
            $wpdb->prepare( 'DELETE a,b,c FROM ' . $wpdb->prefix . 'posts a LEFT JOIN ' . $wpdb->prefix . 'term_relationships b ON (a.ID = b.object_id) LEFT JOIN ' . $wpdb->prefix . 'postmeta c ON (a.ID = c.post_id) WHERE a.post_type = \'%s\';', $part_type )
          );
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
        'post_type' => $part_type,
        'post_status' => 'publish'
      ]);
      switch ( $part_type ) {
        case 'crystal':
          $attributes = ['from_frequency','to_frequency','size','package_option','tolerance','stability','optemp'];
          foreach ($attributes as $key) {
            add_post_meta( $post_id, $key, $part_array[$key] );
          }
          break;
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
      }
      return $name;
    }
}

WP_CLI::add_command( 'foxparts', 'Fox_Parts_CLI' );