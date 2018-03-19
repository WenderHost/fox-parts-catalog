<?php
/**
 * FoxPart CPT
 */
add_action( 'typerocket_loaded', function(){
  // Setup `Part Type` taxonomy
  $part_type = tr_taxonomy('Part Type');
  $part_type->setHierarchical();

  // Setup `foxpart` CPT
  $single_name = 'foxpart';
  $plural_name = 'Fox Parts';
  $foxparts = tr_post_type( $single_name, $plural_name );
  $foxparts->setIcon( 'cogs' );
  $foxparts->setArgument( 'supports', ['title'] );

  // Assign `Part Type` taxonomy to `foxpart`
  $part_type->apply($foxparts);

  $foxparts->setTitleForm(function(){
    global $post;
    echo '<h1>Part: ' . get_the_title( $post ) . '</h1>';
    echo '<style type="text/css">#post-body-content #titlediv{display: none;}</style>';
  });

  tr_meta_box('Part Options')->apply($foxparts);
  function add_meta_content_part_options() {

    // Get the `part_type` term
    global $post;
    $terms = wp_get_post_terms( $post->ID, 'part_type' );

    if( ! $terms ){
      echo '<div class="notice-info inline">Please assign a Part Type for this part.</div>';
      return;
    } else if( 1 < count( $terms ) ){
      echo '<div class="notice-info inline">Multiple Part Types assigned to this part. Please assign only one Part Type.</div>';
    }

    $form = tr_form();
    switch ( $terms[0]->slug ) {
      case 'crystal':
        echo $form->row(
          $form->text('FROM Frequency',['style' => 'max-width: 120px;']),
          $form->text('TO Frequency',['style' => 'max-width: 120px;'])
        );
        $size_options = [
          '1.2x1.0 mm' => 'A',
          '1.6x1.2 mm' => '0',
          '2.0x1.6 mm' => '1',
          '2.5x2.0 mm' => '2',
          '3.2x2.5 mm' => '3',
          '4.0x2.5 mm' => '4',
          '5.0x3.2 mm' => '5',
          '6.0x3.5 mm' => '6',
          '7.0x5.0 mm' => '7',
          '10.0x4.5 mm' => '8',
          '11.0x5.0 mm' => '8',
          'HC49 SMD (4.5mm)' => '4SD',
          'HC49 SMD (3.2mm)' => '9SD',
        ];
        echo $form->row(
          $form->select('Size')->setOptions($size_options),
          $form->text('Package Option',['style' => 'max-width: 120px;'])
        );
        echo $form->row(
          $form->text('Tolerance',['style' => 'max-width: 120px;']),
          $form->text('Stability',['style' => 'max-width: 120px;']),
          $form->text('Load',['style' => 'max-width: 120px;']),
          $form->text('OpTemp',['style' => 'max-width: 120px;'])
        );
        break;

      case 'oscillator':
        echo $form->row(
          $form->text('FROM Frequency',['style' => 'max-width: 120px;']),
          $form->text('TO Frequency',['style' => 'max-width: 120px;'])
        );
        $size_options = [
          '1.2x1.0 mm' => 'A',
          '1.6x1.2 mm' => '0',
          '2.0x1.6 mm' => '1',
          '2.5x2.0 mm' => '2',
          '3.2x2.5 mm' => '3',
          '4.0x2.5 mm' => '4',
          '5.0x3.2 mm' => '5',
          '6.0x3.5 mm' => '6',
          '7.0x5.0 mm' => '7',
          '10.0x4.5 mm' => '8',
          '11.0x5.0 mm' => '8',
          'HC49 SMD (4.5mm)' => '4SD',
          'HC49 SMD (3.2mm)' => '9SD',
        ];
        echo $form->row(
          $form->select('Size')->setOptions($size_options),
          $form->text('Package Option',['style' => 'max-width: 120px;'])
        );
        echo $form->row(
          $form->text('Voltage',['style' => 'max-width: 120px;']),
          $form->text('Stability',['style' => 'max-width: 120px;']),
          $form->text('Load',['style' => 'max-width: 120px;']),
          $form->text('OpTemp',['style' => 'max-width: 120px;'])
        );
        break;

      default:
        echo '<div class="notice-info inline">No form defined for <code> ' . $terms[0]->slug . '</code>.</div>';
        break;
    }




  }
});