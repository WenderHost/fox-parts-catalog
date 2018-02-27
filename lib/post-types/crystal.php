<?php
/**
 * Crystal CPT
 */

add_action( 'typerocket_loaded', function(){
  $single_name = 'crystal';
  $plural_name = 'crystals';

  $crystals = tr_post_type( $single_name, $plural_name );
  $crystals->setIcon( 'diamond' );
  $crystals->setArgument( 'show_in_graphql', true );
  $crystals->setArgument( 'graphql_single_name', $single_name );
  $crystals->setArgument( 'graphql_plural_name', $plural_name );
  $crystals->setArgument( 'supports', ['title'] );

  $crystals->setTitleForm(function(){
    global $post;
    echo '<h1>Part: ' . get_the_title( $post ) . '</h1>';
    echo '<style type="text/css">#post-body-content #titlediv{display: none;}</style>';
  });

  tr_meta_box('Part Options')->apply($crystals);
  function add_meta_content_part_options() {
    $form = tr_form();
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
  }
});