<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wp-resources-history-page">
    <div class="history-controls">
        <div class="history-filters">
            <select id="alert-type-filter">
                <option value=""><?php _e('All Types', 'wp-resources'); ?></option>
                <option value="memory"><?php _e('Memory', 'wp-resources'); ?></option>
                <option value="disk"><?php _e('Disk', 'wp-resources'); ?></option>
                <option value="cpu"><?php _e('CPU', 'wp-resources'); ?></option>
            </select>
            <button id="clear-history" class="button">
                <?php _e('Clear History', 'wp-resources'); ?>
            </button>
        </div>
        <div class="history-pagination">
            <button class="button prev-page" disabled>
                <?php _e('Previous', 'wp-resources'); ?>
            </button>
            <span class="page-info"></span>
            <button class="button next-page" disabled>
                <?php _e('Next', 'wp-resources'); ?>
            </button>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Date', 'wp-resources'); ?></th>
                <th><?php _e('Type', 'wp-resources'); ?></th>
                <th><?php _e('Message', 'wp-resources'); ?></th>
                <th><?php _e('Value', 'wp-resources'); ?></th>
                <th><?php _e('Threshold', 'wp-resources'); ?></th>
            </tr>
        </thead>
        <tbody id="alert-history-list">
            <tr>
                <td colspan="5" class="loading-placeholder">
                    <?php _e('Loading alert history...', 'wp-resources'); ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<style>
.wp-resources-history-page .history-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 1em 0;
    padding: 10px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wp-resources-history-page .history-filters {
    display: flex;
    gap: 10px;
    align-items: center;
}

.wp-resources-history-page .history-pagination {
    display: flex;
    gap: 10px;
    align-items: center;
}

.wp-resources-history-page .page-info {
    min-width: 100px;
    text-align: center;
}

.wp-resources-history-page .loading-placeholder {
    text-align: center;
    padding: 2em;
    color: #666;
}

.wp-resources-history-page .alert-type {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.wp-resources-history-page .alert-type.memory {
    background: #e5f5fa;
    color: #0073aa;
}

.wp-resources-history-page .alert-type.disk {
    background: #fef8e3;
    color: #dba617;
}

.wp-resources-history-page .alert-type.cpu {
    background: #fef1f1;
    color: #d63638;
}
</style> 