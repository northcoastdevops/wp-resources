<div class="wrap">
    <h1><?php _e('WP Resources Monitor', 'wp-resources'); ?> 
        <button id="refresh-resources" class="page-title-action">
            <span class="dashicons dashicons-update"></span> <?php _e('Refresh Now', 'wp-resources'); ?>
        </button>
    </h1>
    
    <div class="resource-info">
        <p>
            <span class="dashicons dashicons-info"></span> 
            <?php _e('Warning thresholds: Memory (80%), Disk (90%), CPU Load (2.0)', 'wp-resources'); ?>
        </p>
    </div>

    <div class="resource-panels">
        <div class="resource-panel">
            <h2><?php _e('Memory Usage', 'wp-resources'); ?></h2>
            <div class="resource-value">
                <span class="label"><?php _e('Used:', 'wp-resources'); ?></span>
                <span id="memory-usage"><?php _e('Loading...', 'wp-resources'); ?></span>
            </div>
            <div class="resource-value">
                <span class="label"><?php _e('Limit:', 'wp-resources'); ?></span>
                <span id="memory-limit"><?php _e('Loading...', 'wp-resources'); ?></span>
            </div>
            <div class="resource-value">
                <span class="label"><?php _e('Usage:', 'wp-resources'); ?></span>
                <span id="memory-percentage"><?php _e('Loading...', 'wp-resources'); ?></span>
            </div>
            <div class="progress-bar">
                <div id="memory-progress" class="progress"></div>
            </div>
        </div>

        <div class="resource-panel">
            <h2><?php _e('Disk Space', 'wp-resources'); ?></h2>
            <div class="resource-value">
                <span class="label"><?php _e('Used:', 'wp-resources'); ?></span>
                <span id="disk-usage"><?php _e('Loading...', 'wp-resources'); ?></span>
            </div>
            <div class="resource-value">
                <span class="label"><?php _e('Free:', 'wp-resources'); ?></span>
                <span id="disk-free"><?php _e('Loading...', 'wp-resources'); ?></span>
            </div>
            <div class="resource-value">
                <span class="label"><?php _e('Total:', 'wp-resources'); ?></span>
                <span id="disk-total"><?php _e('Loading...', 'wp-resources'); ?></span>
            </div>
            <div class="progress-bar">
                <div id="disk-progress" class="progress"></div>
            </div>
        </div>

        <div class="resource-panel">
            <h2><?php _e('CPU Load', 'wp-resources'); ?></h2>
            <div class="resource-value">
                <span class="label"><?php _e('Load:', 'wp-resources'); ?></span>
                <span id="cpu-usage"><?php _e('Loading...', 'wp-resources'); ?></span>
            </div>
            <div class="progress-bar">
                <div id="cpu-progress" class="progress"></div>
            </div>
        </div>
    </div>

    <style>
        .resource-panels {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .resource-panel {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .resource-panel h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .warning {
            color: #dba617;
            font-weight: bold;
        }
        .critical {
            color: #d63638;
            font-weight: bold;
        }
        .progress-bar {
            background: #f5f5f5;
            border-radius: 4px;
            height: 20px;
            margin-top: 10px;
            overflow: hidden;
        }
        .progress {
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
            width: 0;
        }
        .progress.normal {
            background-color: #00a32a; /* WordPress success green */
        }
        .progress.warning {
            background-color: #dba617; /* WordPress warning yellow */
        }
        .progress.critical {
            background-color: #d63638; /* WordPress error red */
        }
        .progress.error {
            background-color: #ccc;
        }
        .resource-info {
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-left: 4px solid #007cba;
        }
        #refresh-resources {
            margin-left: 10px;
        }
        #refresh-resources .dashicons {
            vertical-align: middle;
            margin-top: -2px;
        }
        .resource-value {
            margin: 5px 0;
        }
        .resource-value .label {
            display: inline-block;
            width: 60px;
            font-weight: 600;
        }
    </style>
</div>

<script>
    jQuery(document).ready(function($) {
        $('#refresh-resources').on('click', function() {
            updateResources();
            $(this).find('.dashicons').addClass('spin');
            setTimeout(() => {
                $(this).find('.dashicons').removeClass('spin');
            }, 1000);
        });
    });
</script> 