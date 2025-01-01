# WP Resources

A WordPress plugin that provides comprehensive server resource monitoring and alerting.

## Features

- Real-time monitoring of server resources:
  - Memory usage and limits
  - Disk space utilization
  - CPU load average
  - Resource warning thresholds
  - Resource status tracking
- Alert System:
  - Visual progress bars with color-coded warnings (normal, warning, critical)
  - Audio alerts for critical resource usage
  - Email notifications with customizable frequency (immediate, hourly, daily)
  - Alert history with filtering and pagination
- Monitoring Configuration:
  - Customizable warning and critical thresholds
  - Configurable resource check schedule (hourly, twice daily, daily)
  - Automatic updates every minute
  - Manual refresh option
- Additional Features:
  - Full internationalization support
  - Caching for performance optimization
  - Graceful handling of unsupported features
  - Clean, user-friendly interface

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## Installation

1. Upload the `wp-resources` directory to `/wp-content/plugins/`
2. Activate through WordPress Plugins menu
3. Access WP Resources from the admin menu

## Configuration

1. Go to WP Resources > Settings
2. Set warning thresholds:
   - Memory Usage (warning: 80%, critical: 90%)
   - Disk Space (warning: 80%, critical: 90%)
   - CPU Load (warning: 5.0, critical: 8.0)
3. Configure email notifications (optional):
   - Enable/disable notifications
   - Set recipient email addresses
   - Choose notification frequency
   - Select warning levels for notifications

## Support

For support: https://ncdlabs.com

## License

GPL v2 or later

## Author

Lou Grossi - ncdLabs 