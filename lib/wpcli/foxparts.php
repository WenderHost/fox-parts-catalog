<?php
if( ! class_exists( 'WP_CLI' ) )
  return;

/**
 * Interact with the Fox Parts catalog
 */
class Fox_Parts_CLI extends WP_CLI_Command{
    var $imported = 0;
    var $part_names = []; // Part names found in the CSV
    var $csv_rows = [];
    var $delete_part_series = false;

    var $current_part_series_name = ''; // The current part series we're importing.
    //var $current_part_series = []; // An array of all of our current part series parts in the DB.
    var $current_part_series_count = 0; // Total # of parts in the DB in the part series we're currently processing.
    var $part_series_report_rows = [];

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
     * : **Deprecated** Delete existing parts of the type we're importing. NOTE: If you don't use this flag, we'll skip the part if it already exists.
     *
     * ## EXAMPLES
     *
     *  wp foxparts import parts.csv
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

        // Are we deleting existing part series?
        //$this->delete_part_series = ( array_key_exists( 'delete', $assoc_args ) )? true : false;

        $row = 1;
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

            // Set our current part series
            $part_series = $this->_get_part_series( $part_name );
            if( $part_series != $this->current_part_series_name ){
              $this->_set_current_part_series( $part_series );
            }

            $table_row['name'] = $part_name;
            $table_row['part_type_slug'] = $part_type_slug;

            $created = ( $part_created )? '✅' : '⛔︎' ;
            $exists = ( $part_exists )? '✅' : '⛔︎' ;
            $this->csv_rows[$part_name] = [
              'row'         => $row - 1,
              'part_series' => $part_series,
              'part'        => $part_name,
              'part_data'   => $table_row,
            ];

            $row++;
        }
        fclose( $handle );
        $this->_save_part_series_report_row();

        // Run the import
        $part_series_report = $this->part_series_report_rows;
        foreach( $part_series_report as $report_row ){
          // Remove existing Part Series from the database:
          $this->_delete_part_series( $report_row['part_series'] );

          // Import the parts from the CSV:
          $progress_message = '✅ Creating ' . $report_row['total_in_csv'] . ' parts in `' . $report_row['part_series'] . '` Part Series:';
          $progress = \WP_CLI\Utils\make_progress_bar( $progress_message, $report_row['total_in_csv'] );
          foreach( $this->csv_rows as $row ){
            if( $report_row['part_series'] == $row['part_series'] ){
              $this->create_part( $row['part_data'] );
              $progress->tick();
            }
          }
          $progress->finish();
        }

        if( 0 < $this->imported )
          WP_CLI::success( $this->imported . ' parts created/imported.' );
        $this->part_series_report();
    }


    /**
     * Creates a part.
     *
     * @param      array    $part_array  The part array
     *
     * @return     int  Post ID of the new part.
     */
    private function create_part( $part_array ){
      if( $this->_part_exists( $part_array['name'] ) ){
        WP_CLI::line('SKIPPING: Part `' . $part_array['name'] . '` exists.');
        return false;
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

        case 'sso':
          $attributes = ['from_frequency','to_frequency','size','enable_type','voltage','spread','optemp'];
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
        //WP_CLI::success('Created `' . $part_array['name'] . '`');
        $this->imported++;
      }
      return $post_id;
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

        case 'sso':
          $name_components = ['F','part_type','size','enable_type','voltage','spread','optemp'];
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
     * Gets the part series from a part name.
     *
     * @param      string  $part_name  The part name
     *
     * @return     string  The part series.
     */
    private function _get_part_series( $part_name ){
      $start = ( 'F' == substr( $part_name, 0, 1 ) )? 1 : 0 ;
      $part_series = substr( $part_name, $start, 4 );
      return $part_series;
    }

    /**
     * Returns the part type slug (e.g. `crystal`, `crystal-khz`, `oscillator`, etc).
     *
     * @param      string  $part_type  The part type code
     *
     * @return     string  The part type slug.
     */
    private function get_part_type_slug( $part_type ){
      $part_types_array = FOXPC_PART_TYPES;

      if( ! array_key_exists( $part_type, $part_types_array ) )
        WP_CLI::error( 'No Part Type slug has been defined for part type `' . $part_type . '`.' );

      return $part_types_array[$part_type];
    }

    /**
     * Determines if part exists.
     *
     * @param      string   $part_name  The part name
     *
     * @return     boolean  True if part exists, False otherwise.
     */
    private function _part_exists( $part_name ){
      $part_exists = get_page_by_title( $part_name, OBJECT, 'foxpart' );
      if( is_null( $part_exists ) || is_wp_error( $part_exists ) ) {
        return false;
      } else {
        return true;
      }
    }

    /**
     * Displays the Part Series Report.
     */
    function part_series_report(){
      $report = $this->part_series_report_rows;
      WP_CLI::line("\n🔔 PART SERIES REPORT:");
      WP_CLI\Utils\format_items( 'table', $report, ['part_series','total_in_db','total_in_csv'] );
    }

    /**
     * Saves a part series report row.
     */
    private function _save_part_series_report_row(){
      $total_in_csv = array_count_values( array_column( $this->csv_rows, 'part_series' ) )[ $this->current_part_series_name ];
      $this->part_series_report_rows[] = [
        'part_series'   => $this->current_part_series_name,
        'total_in_db'   => $this->current_part_series_count,
        'total_in_csv'  => $total_in_csv,
      ];
    }

    /**
     * Deletes a Part Series.
     *
     * @param      string  $part_series  The part series
     */
    private function _delete_part_series( $part_series ){
      $foxparts = get_posts([
        'post_type'   => 'foxpart',
        's'           => $part_series,
        'order'       => 'ASC',
        'orderby'     => 'title',
        'numberposts' => -1,
      ]);
      $total_parts_in_this_series = count( $foxparts );
      $progress = \WP_CLI\Utils\make_progress_bar( '🚨 Deleting ' . $total_parts_in_this_series . ' parts in `' . $part_series . '` Part Series:', $total_parts_in_this_series );
      foreach( $foxparts as $part ){
        wp_delete_post( $part->ID, true );
        $progress->tick();
      }
      $progress->finish();
    }

    /**
     * Sets the current part series.
     *
     * @param      string  $part_series  The part series
     */
    private function _set_current_part_series( $part_series ){
      // If we have a "current part series", we need to save a row
      // in our report array before we switch to a new part series:
      if( 0 < $this->current_part_series_count )
        $this->_save_part_series_report_row();

      $this->current_part_series_name = $part_series;
      $foxparts = get_posts([
        'post_type'   => 'foxpart',
        's'           => $part_series,
        'order'       => 'ASC',
        'orderby'     => 'title',
        'numberposts' => -1,
      ]);
      $total_parts_in_this_series = count( $foxparts );
      $this->current_part_series_count = $total_parts_in_this_series;
    }
}

WP_CLI::add_command( 'foxparts', 'Fox_Parts_CLI' );