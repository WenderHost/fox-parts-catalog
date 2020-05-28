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
  $foxparts->setArgument( 'supports', ['title','thumbnail'] );

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
    $part_type = $terms[0]->slug;
    switch ( $part_type ) {
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

      case 'crystal-khz':
        $size_options = [
          '1.2x1.0 mm (2 pad)' => '121',
          '1.2x1.0 mm (4 pad)' => '124',
          '1.6x1.0 mm' => '161',
          '2.0x1.2 mm' => '122',
          '2.0x1.2 mm (AEC-Q200)' => '12A',
          '3.2x1.5 mm (AEC-Q200)' => '13A',
          '3.2x1.5 mm ESR 70K Ohm (Std)' => '135',
          '3.2x1.5 mm ESR 50K Ohm (Optional)' => '13L',
          '4.1x1.5 mm' => '145',
          '4.9x1.8 mm' => '255',
          '5.0x1.5 mm' => 'T15',
          '6.0x2.0 mm' => 'T26',
          '7.0x1.5 mm' => 'FSX',
          '8.0x3.0 mm' => 'T38',
          '8.7x3.7 mm' => 'FSR',
          '10.4x4.0 mm' => 'FSM',
        ];
        echo $form->row(
          $form->select('Size')->setOptions($size_options),
          $form->text('FROM Frequency',['style' => 'max-width: 120px;']),
          $form->text('TO Frequency',['style' => 'max-width: 120px;'])
        );
        echo $form->row(
          $form->text('Tolerance',['style' => 'max-width: 120px;']),
          $form->text('Stability',['style' => 'max-width: 120px;']),
          $form->text('OpTemp',['style' => 'max-width: 120px;'])
        );
        break;

      case 'oscillator':
      case 'tcxo':
      case 'vcxo':
        echo $form->row(
          $form->text('FROM Frequency',['style' => 'max-width: 120px;']),
          $form->text('TO Frequency',['style' => 'max-width: 120px;'])
        );
        switch ($part_type) {
          case 'oscillator':
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
            break;

          default:
            // TCXO size options
            $size_options = [
              '2.0x1.6 mm' => '1',
              '2.5x2.0 mm' => '2',
              '3.2x2.5 mm' => '3',
              '5.0x3.2 mm' => '5',
              '7.0x5.0 mm' => '7',
              '11.4x9.6 mm' => '9',
            ];
            break;
        }

        $pin_1 = ( 'tcxo' == $part_type )? $form->text('Pin 1',['style' => 'max-width: 120px;']) : '';


        echo $form->row(
          $form->select('Size')->setOptions($size_options),
          $form->text('Output',['style' => 'max-width: 120px;']),
          $pin_1
        );
        echo $form->row(
          $form->text('Voltage',['style' => 'max-width: 120px;']),
          $form->text('Stability',['style' => 'max-width: 120px;']),
          $form->text('OpTemp',['style' => 'max-width: 120px;'])
        );
        break;

      case 'sso':
        echo $form->row(
          $form->text('FROM Frequency',['style' => 'max-width: 120px;']),
          $form->text('TO Frequency',['style' => 'max-width: 120px;'])
        );
        echo $form->row(
          $form->select('Size')->setOptions([
            '5.0x3.2mm' => '5',
            '7.0x5.0mm' => '7',
          ]),
          $form->select('Enable Type')->setOptions([
            'Tristate'  => 'B',
            'Standby'   => 'S',
          ]),
          $form->select('Voltage')->setOptions([
            '2.5 Volts ±5%'   => 'H',
            '3.3 Volts ±10%'  => 'C',
          ]),
          $form->select('Spread')->setOptions([
            '±0.25% Center Spread'  => 'A',
            '±0.5% Center Spread'   => 'B',
            '±0.75% Center Spread'  => 'C',
            '±1.0% Center Spread'   => 'D',
            '±1.5% Center Spread'   => 'E',
            '±2.0% Center Spread'   => 'F',
            '-0.5% Down Spread'     => 'G',
            '-1.0% Down Spread'     => 'H',
            '-1.5% Down Spread'     => 'J',
            '-2.0% Down Spread'     => 'K',
            '-3.0% Down Spread'     => 'L',
            '-4.0% Down Spread'     => 'M',
            '±0.125 Center Spread'  => 'N',
            '-0.25 Down Spread'     => 'P',
          ]),
          $form->select('OpTemp')->setOptions([
            '0 to +70C'   => 'C',
            '40 to +85C'  => 'M',
          ])
        );
        break;

      default:
        echo '<div class="notice-info inline">No form defined for <code> ' . $terms[0]->slug . '</code>.</div>';
        break;
    }




  }
});