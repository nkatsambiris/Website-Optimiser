<?php
/**
 * Gravity Forms Conversion Events functionality for Website Optimiser Plugin
 * Tracks which forms have Google Analytics conversion events configured
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Get all Gravity Forms with their conversion event status
 */
function meta_description_boy_get_gravity_forms_conversion_events_data() {
    // Check if Gravity Forms is active
    if (!class_exists('GFAPI')) {
        return array();
    }

    $forms = GFAPI::get_forms();
    $conversion_data = get_option('meta_description_boy_conversion_events_data', array());

    $forms_data = array();

    foreach ($forms as $form) {
        $form_id = $form['id'];
        $form_data = isset($conversion_data[$form_id]) ? $conversion_data[$form_id] : array();

        $forms_data[] = array(
            'id' => $form_id,
            'title' => $form['title'],
            'status' => isset($form_data['status']) ? $form_data['status'] : 'pending',
            'created_by' => isset($form_data['created_by']) ? $form_data['created_by'] : '',
            'created_date' => isset($form_data['created_date']) ? $form_data['created_date'] : '',
            'notes' => isset($form_data['notes']) ? $form_data['notes'] : ''
        );
    }

    return $forms_data;
}

/**
 * Check Gravity Forms conversion events status
 */
function meta_description_boy_check_gravity_forms_conversion_events_status() {
    // Check if plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $gravity_forms_plugin = 'gravityforms/gravityforms.php';
    $gf_installed = false;
    $gf_active = false;
    $gf_version = '';

    // Check if Gravity Forms is installed and active
    if (is_plugin_active($gravity_forms_plugin)) {
        $gf_installed = true;
        $gf_active = true;
        if (file_exists(WP_PLUGIN_DIR . '/' . $gravity_forms_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $gravity_forms_plugin);
            $gf_version = $plugin_data['Version'] ?? '';
        }
    } elseif (file_exists(WP_PLUGIN_DIR . '/' . $gravity_forms_plugin)) {
        $gf_installed = true;
        $gf_active = false;
    }

    if (!$gf_installed) {
        return array(
            'gf_installed' => false,
            'gf_active' => false,
            'gf_version' => '',
            'total_forms' => 0,
            'configured_forms' => 0,
            'pending_forms' => 0,
            'not_required_forms' => 0,
            'status' => 'Gravity Forms Missing',
            'message' => 'Gravity Forms plugin is not installed',
            'class' => 'status-error'
        );
    } elseif (!$gf_active) {
        return array(
            'gf_installed' => true,
            'gf_active' => false,
            'gf_version' => '',
            'total_forms' => 0,
            'configured_forms' => 0,
            'pending_forms' => 0,
            'not_required_forms' => 0,
            'status' => 'Gravity Forms Inactive',
            'message' => 'Gravity Forms is installed but not activated',
            'class' => 'status-error'
        );
    }

    // Get forms data
    $forms_data = meta_description_boy_get_gravity_forms_conversion_events_data();
    $total_forms = count($forms_data);

    if ($total_forms === 0) {
        return array(
            'gf_installed' => true,
            'gf_active' => true,
            'gf_version' => $gf_version,
            'total_forms' => 0,
            'configured_forms' => 0,
            'pending_forms' => 0,
            'not_required_forms' => 0,
            'status' => 'No Forms Found',
            'message' => 'No forms have been created yet',
            'class' => 'status-warning'
        );
    }

    // Count forms by status
    $configured_forms = 0;
    $pending_forms = 0;
    $not_required_forms = 0;

    foreach ($forms_data as $form) {
        switch ($form['status']) {
            case 'added':
                $configured_forms++;
                break;
            case 'not_required':
                $not_required_forms++;
                break;
            default:
                $pending_forms++;
                break;
        }
    }

    // Determine overall status
    if ($pending_forms === 0) {
        $status = 'All Forms Configured';
        $message = 'All forms have been reviewed for conversion events';
        $class = 'status-good';
    } else {
        $status = $pending_forms . ' Form' . ($pending_forms > 1 ? 's' : '') . ' Pending';
        $message = $pending_forms . ' form' . ($pending_forms > 1 ? 's' : '') . ' need conversion event configuration';
        $class = 'status-warning';
    }

    return array(
        'gf_installed' => true,
        'gf_active' => true,
        'gf_version' => $gf_version,
        'total_forms' => $total_forms,
        'configured_forms' => $configured_forms,
        'pending_forms' => $pending_forms,
        'not_required_forms' => $not_required_forms,
        'status' => $status,
        'message' => $message,
        'class' => $class
    );
}

/**
 * Handle AJAX request to update form conversion event status
 */
function meta_description_boy_update_conversion_event_status() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $form_id = intval($_POST['form_id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    $created_by = sanitize_text_field($_POST['created_by'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    if (!$form_id || !in_array($status, array('added', 'not_required', 'pending'))) {
        wp_die(json_encode(array('success' => false, 'message' => 'Invalid form ID or status')));
    }

    // For 'added' status, require created_by field
    if ($status === 'added' && empty($created_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required when marking as added')));
    }

    // Get existing data
    $conversion_data = get_option('meta_description_boy_conversion_events_data', array());

    // Update form data
    $conversion_data[$form_id] = array(
        'status' => $status,
        'created_by' => $status === 'added' ? $created_by : '',
        'created_date' => $status === 'added' ? current_time('mysql') : '',
        'notes' => $notes
    );

    // Save updated data
    update_option('meta_description_boy_conversion_events_data', $conversion_data);

    wp_die(json_encode(array('success' => true, 'message' => 'Conversion event status updated')));
}
add_action('wp_ajax_meta_description_boy_update_conversion_event_status', 'meta_description_boy_update_conversion_event_status');

/**
 * Render Gravity Forms conversion events section
 */
function meta_description_boy_render_gravity_forms_conversion_events_section() {
    $conversion_status = meta_description_boy_check_gravity_forms_conversion_events_status();
    $forms_data = array();

    if ($conversion_status['gf_active']) {
        $forms_data = meta_description_boy_get_gravity_forms_conversion_events_data();
    }
    ?>
    <div class="seo-stat-item <?php echo $conversion_status['class']; ?>">
        <div class="stat-icon">📊</div>
        <div class="stat-content">
            <h4>Conversion Events</h4>
            <div class="stat-status <?php echo $conversion_status['class']; ?>">
                <?php echo $conversion_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $conversion_status['message']; ?>
                <?php if (!empty($conversion_status['gf_version'])): ?>
                    <br><small>Gravity Forms: v<?php echo $conversion_status['gf_version']; ?></small>
                <?php endif; ?>

                <?php if ($conversion_status['total_forms'] > 0): ?>
                    <br><small>
                        <?php echo $conversion_status['configured_forms']; ?> configured,
                        <?php echo $conversion_status['not_required_forms']; ?> not required,
                        <?php echo $conversion_status['pending_forms']; ?> pending
                    </small>
                <?php endif; ?>
            </div>

            <?php if (!empty($forms_data)): ?>
            <div class="conversion-events-list" style="margin-top: 15px;">
                <div class="conversion-events-toggle" style="text-align: center;">
                    <button type="button" class="button button-small" onclick="toggleConversionEventsList()">
                        <span id="toggle-text">Show Forms</span> (<?php echo count($forms_data); ?>)
                    </button>
                </div>

                <div id="conversion-events-forms" style="display: none; margin-top: 10px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                    <?php foreach ($forms_data as $form): ?>
                    <div class="conversion-event-form" style="padding: 12px; border-bottom: 1px solid #eee; background: #f9f9f9;" data-form-id="<?php echo $form['id']; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <strong style="flex: 1;"><?php echo esc_html($form['title']); ?></strong>
                            <span class="status-indicator status-<?php echo $form['status']; ?>" style="
                                padding: 2px 8px;
                                border-radius: 12px;
                                font-size: 11px;
                                font-weight: bold;
                                text-transform: uppercase;
                                <?php
                                switch ($form['status']) {
                                    case 'added':
                                        echo 'background: #d4edda; color: #155724;';
                                        break;
                                    case 'not_required':
                                        echo 'background: #d1ecf1; color: #0c5460;';
                                        break;
                                    default:
                                        echo 'background: #fff3cd; color: #856404;';
                                        break;
                                }
                                ?>
                            ">
                                <?php
                                switch ($form['status']) {
                                    case 'added':
                                        echo 'Added';
                                        break;
                                    case 'not_required':
                                        echo 'Not Required';
                                        break;
                                    default:
                                        echo 'Pending';
                                        break;
                                }
                                ?>
                            </span>
                        </div>

                        <?php if ($form['status'] === 'added' && !empty($form['created_by'])): ?>
                            <small style="color: #666; display: block; margin-bottom: 8px;">
                                Added by: <strong><?php echo esc_html($form['created_by']); ?></strong>
                                <?php if (!empty($form['created_date'])): ?>
                                    on <?php echo date('M j, Y', strtotime($form['created_date'])); ?>
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>

                        <?php if (!empty($form['notes'])): ?>
                            <div style="background: #fff; padding: 8px; border-radius: 3px; margin-bottom: 8px;">
                                <small><strong>Notes:</strong> <?php echo esc_html($form['notes']); ?></small>
                            </div>
                        <?php endif; ?>

                        <div class="conversion-event-actions">
                            <?php if ($form['status'] === 'pending'): ?>
                                <button type="button" class="button button-small" onclick="showConversionEventForm(<?php echo $form['id']; ?>)">
                                    Configure
                                </button>
                            <?php else: ?>
                                <button type="button" class="button button-small" onclick="showConversionEventForm(<?php echo $form['id']; ?>)">
                                    Edit
                                </button>
                                <button type="button" class="button button-small" onclick="resetConversionEventStatus(<?php echo $form['id']; ?>)" style="margin-left: 5px;">
                                    Reset
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="stat-action">
                <?php if (!$conversion_status['gf_installed']): ?>
                    <!-- Gravity Forms is not installed -->
                <?php elseif (!$conversion_status['gf_active']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate Gravity Forms
                    </a>
                <?php elseif ($conversion_status['total_forms'] === 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_new_form'); ?>" class="button button-small">
                        Create First Form
                    </a>
                <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_edit_forms'); ?>" class="button button-small">
                        Manage Forms
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Conversion Event Configuration Modal -->
    <div id="conversion-event-modal" class="conversion-event-modal" style="display: none;">
        <div class="conversion-event-modal-content">
            <div class="conversion-event-modal-header">
                <h3>Configure Conversion Event</h3>
                <span class="conversion-event-modal-close">&times;</span>
            </div>
            <div class="conversion-event-modal-body">
                <form id="conversion-event-form">
                    <input type="hidden" id="conversion-event-form-id" name="form_id" value="">

                    <div class="form-field">
                        <label><strong>Form:</strong> <span id="conversion-event-form-title"></span></label>
                    </div>

                    <div class="form-field">
                        <label for="conversion-event-status">Status:</label>
                        <select id="conversion-event-status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="added">Added</option>
                            <option value="not_required">Not Required</option>
                        </select>
                    </div>

                    <div class="form-field" id="created-by-field" style="display: none;">
                        <label for="conversion-event-created-by">Created by:</label>
                        <input type="text" id="conversion-event-created-by" name="created_by" placeholder="Your name">
                    </div>

                    <div class="form-field">
                        <label for="conversion-event-notes">Notes (optional):</label>
                        <textarea id="conversion-event-notes" name="notes" rows="3" placeholder="Add any additional notes about this conversion event..."></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="button button-primary">
                            Save Configuration
                        </button>
                        <button type="button" class="button button-secondary conversion-event-modal-cancel">
                            Cancel
                        </button>
                    </div>
                </form>
                <div id="conversion-event-status-message" class="conversion-event-status-message" style="display: none;"></div>
            </div>
        </div>
    </div>

    <style>
    .conversion-event-modal {
        display: none;
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .conversion-event-modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
    }

    .conversion-event-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #f0f0f1;
        background-color: #f9f9f9;
    }

    .conversion-event-modal-header h3 {
        margin: 0;
        font-size: 18px;
        color: #23282d;
    }

    .conversion-event-modal-close {
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #666;
        line-height: 1;
    }

    .conversion-event-modal-close:hover {
        color: #dc3232;
    }

    .conversion-event-modal-body {
        padding: 20px;
    }

    .conversion-event-status-message {
        margin-top: 15px;
        padding: 10px;
        border-radius: 4px;
        font-size: 14px;
    }

    .conversion-event-status-message.success {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    .conversion-event-status-message.error {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }

    @media (max-width: 782px) {
        .conversion-event-modal-content {
            width: 95%;
            margin: 2% auto;
        }
    }
    </style>

    <script>
    let currentFormData = {};

    function toggleConversionEventsList() {
        const formsList = document.getElementById('conversion-events-forms');
        const toggleText = document.getElementById('toggle-text');

        if (formsList.style.display === 'none') {
            formsList.style.display = 'block';
            toggleText.textContent = 'Hide Forms';
        } else {
            formsList.style.display = 'none';
            toggleText.textContent = 'Show Forms';
        }
    }

    function showConversionEventForm(formId) {
        // Find form data
        const formElement = document.querySelector(`[data-form-id="${formId}"]`);
        const formTitle = formElement.querySelector('strong').textContent;
        const statusIndicator = formElement.querySelector('.status-indicator');
        const currentStatus = statusIndicator.classList.contains('status-added') ? 'added' :
                            statusIndicator.classList.contains('status-not_required') ? 'not_required' : 'pending';

        // Populate modal
        document.getElementById('conversion-event-form-id').value = formId;
        document.getElementById('conversion-event-form-title').textContent = formTitle;
        document.getElementById('conversion-event-status').value = currentStatus;

        // Clear fields
        document.getElementById('conversion-event-created-by').value = '';
        document.getElementById('conversion-event-notes').value = '';

        // Toggle created_by field visibility
        toggleCreatedByField(currentStatus);

        // Show modal
        document.getElementById('conversion-event-modal').style.display = 'block';
    }

    function toggleCreatedByField(status) {
        const createdByField = document.getElementById('created-by-field');
        if (status === 'added') {
            createdByField.style.display = 'block';
            document.getElementById('conversion-event-created-by').required = true;
        } else {
            createdByField.style.display = 'none';
            document.getElementById('conversion-event-created-by').required = false;
        }
    }

    function resetConversionEventStatus(formId) {
        if (!confirm('Are you sure you want to reset this conversion event status?')) {
            return;
        }

        updateConversionEventStatus(formId, 'pending', '', '');
    }

    function updateConversionEventStatus(formId, status, createdBy, notes) {
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_update_conversion_event_status',
            form_id: formId,
            status: status,
            created_by: createdBy,
            notes: notes,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Status dropdown change
        const statusSelect = document.getElementById('conversion-event-status');
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                toggleCreatedByField(this.value);
            });
        }

        // Modal close events
        const modal = document.getElementById('conversion-event-modal');
        const closeBtn = document.querySelector('.conversion-event-modal-close');
        const cancelBtn = document.querySelector('.conversion-event-modal-cancel');

        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Form submission
        const form = document.getElementById('conversion-event-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                const status = formData.get('status');
                const createdBy = formData.get('created_by');
                const notes = formData.get('notes');
                const formId = formData.get('form_id');

                if (status === 'added' && !createdBy.trim()) {
                    alert('Please enter your name when marking as added.');
                    return;
                }

                updateConversionEventStatus(formId, status, createdBy, notes);
            });
        }
    });
    </script>
    <?php
}