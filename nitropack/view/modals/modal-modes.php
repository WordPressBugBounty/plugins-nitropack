<?php
$table_columns = array('standard' => __('Standard', 'nitropack'), 'medium' => __('Medium', 'nitropack'), 'strong' => __('Strong', 'nitropack'), 'luda' => __('Ludicrous', 'nitropack'));
$table_cells = array(
    array(
        'name' => __('Add HTML preconnects', 'nitropack'), 'features' => array('standard', 'medium', 'strong', 'luda'),
    ),
    array(
        'name' => __('Minify resources', 'nitropack'), 'features' => array('medium', 'strong', 'luda'),
    ),
    array(
        'name' => __('Combine JavaScript', 'nitropack'), 'features' => array('', '', '', ''),
    ),
    array(
        'name' => __('Combine CSS', 'nitropack'), 'features' => array('medium', 'strong', 'luda'), 'sub' => true
    ),
    array(
        'name' => __('Merge screen and all media styles', 'nitropack'), 'features' => array('medium', 'strong', 'luda'),
    ),
    array(
        'name' => __('Combine CSS', 'nitropack'), 'features' => array('medium', 'strong', 'luda'),
    ),
    array(
        'name' => __('Generate critical CSS', 'nitropack'), 'features' => array('medium', 'strong', 'luda'),
    ),
    array(
        'name' => __('Remove render-blocking resources', 'nitropack'), 'features' => array('medium', 'strong', 'luda'),
    ),
    array(
        'name' => __('Use resource loader script', 'nitropack'), 'features' => array('strong', 'luda'), 'sub' => true
    ),
    array(
        'name' => __('Delay loading of non-critical resources', 'nitropack'), 'features' => array('luda'), 'sub' => true
    ),
    array(
        'name' => __('Resource loading strategy', 'nitropack'), 'features' => array('standard' => 'Styles first', 'medium' => 'Styles first', 'strong' => 'Styles first', 'luda' => 'Styles first'), 'sub' => true
    ),
    array(
        'name' => __('Images lazy-loading', 'nitropack'), 'features' => array('medium', 'strong', 'luda'),
    ),
    array(
        'name' => __('Size images preemtively', 'nitropack'), 'features' => array('medium', 'strong', 'luda'), 'sub' => true
    ),
    array(
        'name' => __('iFrames lazy load', 'nitropack'), 'features' => array('medium', 'strong', 'luda'),
    ),
    array(
        'name' => __('Image optimization', 'nitropack'), 'features' => array('standard', 'medium', 'strong', 'luda'),
    ),
    array(
        'name' => __('Image quality', 'nitropack'), 'features' => array('standard' => '100%', 'medium' => '80%', 'strong' => '80%', 'luda' => '80%'), 'sub' => true
    ),
    array(
        'name' => __('Override font rendering behavior', 'nitropack'), 'features' => array('strong', 'luda'),
    ),
    array(
        'name' => __('Font-display value', 'nitropack'), 'features' => array('standard' => '', 'medium' => '', 'strong' => 'swap', 'luda' => 'swap'), 'sub' => true,
    ),
); ?>

<div id="modes-modal" data-modal-backdrop="" tabindex="-1" aria-hidden="true" class="hidden modal-wrapper">
    <div class="modal-container" style="max-width: 720px;">
        <div class="modal-inner">
            <!-- Modal header -->
            <div class="modal-header">
                <h3><?php esc_html_e('Modes comparison', 'nitropack'); ?></h3>
                <button type="button" class="close-modal" data-modal-hide="modes-modal">
                    <svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.293031 1.29308C0.480558 1.10561 0.734866 1.00029 1.00003 1.00029C1.26519 1.00029 1.5195 1.10561 1.70703 1.29308L6.00003 5.58608L10.293 1.29308C10.3853 1.19757 10.4956 1.12139 10.6176 1.06898C10.7396 1.01657 10.8709 0.988985 11.0036 0.987831C11.1364 0.986677 11.2681 1.01198 11.391 1.06226C11.5139 1.11254 11.6255 1.18679 11.7194 1.28069C11.8133 1.37458 11.8876 1.48623 11.9379 1.60913C11.9881 1.73202 12.0134 1.8637 12.0123 1.99648C12.0111 2.12926 11.9835 2.26048 11.9311 2.38249C11.8787 2.50449 11.8025 2.61483 11.707 2.70708L7.41403 7.00008L11.707 11.2931C11.8892 11.4817 11.99 11.7343 11.9877 11.9965C11.9854 12.2587 11.8803 12.5095 11.6948 12.6949C11.5094 12.8803 11.2586 12.9855 10.9964 12.9878C10.7342 12.99 10.4816 12.8892 10.293 12.7071L6.00003 8.41408L1.70703 12.7071C1.51843 12.8892 1.26583 12.99 1.00363 12.9878C0.741432 12.9855 0.49062 12.8803 0.305212 12.6949C0.119804 12.5095 0.0146347 12.2587 0.0123563 11.9965C0.0100779 11.7343 0.110873 11.4817 0.293031 11.2931L4.58603 7.00008L0.293031 2.70708C0.10556 2.51955 0.000244141 2.26525 0.000244141 2.00008C0.000244141 1.73492 0.10556 1.48061 0.293031 1.29308Z" fill="#1B004E" />
                    </svg>
                    <span class="sr-only"><?php esc_html_e('Close modal', 'nitropack'); ?></span>
                </button>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <div class="modern-table">
                    <div class="grid grid-cols-2 col-span-2 thead">
                        <div></div>
                        <div class="grid grid-cols-4">
                            <?php foreach ($table_columns as $table_column) : ?>
                                <div class="th">
                                    <?php echo $table_column; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="scrollbar-default overflow-auto max-h-full">
                        <?php
                        foreach ($table_cells as $feature) : ?>
                            <div class="col-span-2 grid grid-cols-2 tr">
                                <div class="flex gap-2 td feature-name">
                                    <?php if (isset($feature['sub'])) : ?>
                                        <svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-gray-500">
                                            <path d="M1 12H0.5V12.5H1V12ZM9.35355 12.3536C9.54882 12.1583 9.54882 11.8417 9.35355 11.6464L6.17157 8.46447C5.97631 8.2692 5.65973 8.2692 5.46447 8.46447C5.2692 8.65973 5.2692 8.97631 5.46447 9.17157L8.29289 12L5.46447 14.8284C5.2692 15.0237 5.2692 15.3403 5.46447 15.5355C5.65973 15.7308 5.97631 15.7308 6.17157 15.5355L9.35355 12.3536ZM0.5 0V12H1.5V0H0.5ZM1 12.5H9V11.5H1V12.5Z" fill="currentColor"></path>
                                        </svg>
                                    <?php endif; ?>
                                    <?php echo $feature['name']; ?>
                                </div>

                                <div class="grid grid-cols-4 td modes">
                                    <?php if ($feature['features']) :
                                        foreach ($table_columns as $key => $column) {
                                            echo '<div class="flex justify-center items-center mode">';
                                            // Check if the feature is available in the current column
                                            $has_feature = in_array($key, $feature['features']);
                                            // Display checkbox if the feature is available                                           
                                            if ($has_feature) {
                                                echo '<svg width="16" height="11" viewBox="0 0 16 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M14.6666 1L5.49992 10.1667L1.33325 6" stroke="#4600CC" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                ';
                                            }
                                            if (array_key_exists($key, $feature['features'])) {
                                                // Display the text specified in the features
                                                echo '' . $feature['features'][$key] . '';
                                            }
                                            echo '</div>';
                                        }
                                    ?>

                                    <?php
                                    endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Modal footer -->
                <div class="modal-footer" style="margin-bottom: 0">
                    <button data-modal-hide="modes-modal" type="button" class="btn btn-secondary ml-auto"><?php esc_html_e('Close', 'nitropack'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>