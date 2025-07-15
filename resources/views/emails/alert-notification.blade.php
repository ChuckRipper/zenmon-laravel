{{-- Alert Notification Email Template (HTML) --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $systemInfo['app_name'] }} Alert Notification</title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: {{ $colorScheme['bg'] }};
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Header */
        .email-header {
            background: linear-gradient(135deg, {{ $colorScheme['primary'] }}, {{ $colorScheme['primary'] }}dd);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .email-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .email-header .subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        /* Priority badge */
        .priority-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 15px 0 5px 0;
            background-color: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Content */
        .email-content {
            padding: 30px 20px;
        }
        
        .alert-summary {
            background-color: {{ $colorScheme['secondary'] }};
            border-left: 4px solid {{ $colorScheme['primary'] }};
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 0 6px 6px 0;
        }
        
        .alert-summary h2 {
            color: {{ $colorScheme['text'] }};
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .alert-message {
            font-size: 16px;
            color: {{ $colorScheme['text'] }};
            font-weight: 500;
        }
        
        /* Details table */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            background-color: #ffffff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .details-table th,
        .details-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .details-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            width: 35%;
        }
        
        .details-table td {
            color: #212529;
        }
        
        .details-table tr:last-child td,
        .details-table tr:last-child th {
            border-bottom: none;
        }
        
        /* Value highlighting */
        .value-highlight {
            font-weight: 600;
            color: {{ $colorScheme['primary'] }};
        }
        
        .threshold-exceeded {
            background-color: #fff5f5;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #fed7d7;
        }
        
        /* Action buttons */
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        
        .btn {
            display: inline-block;
            padding: 14px 28px;
            margin: 0 10px 10px 0;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: {{ $colorScheme['primary'] }};
            color: white;
            border: 2px solid {{ $colorScheme['primary'] }};
        }
        
        .btn-secondary {
            background-color: transparent;
            color: {{ $colorScheme['primary'] }};
            border: 2px solid {{ $colorScheme['primary'] }};
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* Footer */
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .email-footer p {
            color: #6c757d;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .email-footer a {
            color: {{ $colorScheme['primary'] }};
            text-decoration: none;
        }
        
        /* Status indicators */
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-active { background-color: #dc3545; }
        .status-warning { background-color: #ffc107; }
        .status-resolved { background-color: #28a745; }
        
        /* Mobile responsiveness */
        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 0;
            }
            
            .email-header,
            .email-content {
                padding: 20px 15px;
            }
            
            .details-table th,
            .details-table td {
                padding: 10px;
            }
            
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        {{-- Header --}}
        <div class="email-header">
            <div class="priority-badge">
                @if($type === 'resolved')
                    ✅ Resolved
                @elseif($type === 'test')
                    🧪 Test
                @elseif($alert->alert_level === 'Critical')
                    🔥 Critical Alert
                @elseif($alert->alert_level === 'Warning')
                    ⚠️ Warning Alert
                @else
                    📊 Alert
                @endif
            </div>
            
            <h1>{{ $systemInfo['app_name'] }}</h1>
            <div class="subtitle">Monitoring & Alert System</div>
        </div>

        {{-- Content --}}
        <div class="email-content">
            {{-- Alert Summary --}}
            <div class="alert-summary">
                <h2>
                    <span class="status-indicator status-{{ $priorityLevel }}"></span>
                    @if($type === 'resolved')
                        Alert Resolved: {{ $alert->host->hostname }}
                    @else
                        {{ $alert->alert_level }} Alert: {{ $alert->host->hostname }}
                    @endif
                </h2>
                <div class="alert-message">{{ $alert->alert_message }}</div>
            </div>

            {{-- Alert Details --}}
            <table class="details-table">
                <tr>
                    <th>🖥️ Host</th>
                    <td>
                        <strong>{{ $alert->host->hostname }}</strong><br>
                        <small style="color: #6c757d;">{{ $alert->host->ip_address }}</small>
                    </td>
                </tr>
                <tr>
                    <th>📊 Metric Type</th>
                    <td>{{ $alert->metricType->type_name }}</td>
                </tr>
                @if($type !== 'resolved')
                <tr>
                    <th>📈 Current Value</th>
                    <td>
                        <span class="value-highlight threshold-exceeded">
                            {{ number_format($alert->current_value, 2) }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>🎯 Threshold</th>
                    <td>{{ number_format($alert->threshold_value, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <th>📅 {{ $type === 'resolved' ? 'Resolved' : 'Detected' }}</th>
                    <td>
                        {{ $alert->created_at->format('Y-m-d H:i:s') }}<br>
                        <small style="color: #6c757d;">{{ $alert->created_at->diffForHumans() }}</small>
                    </td>
                </tr>
                @if($type === 'resolved' && $alert->status === 'Closed' && $alert->close_comment)
                <tr>
                    <th>💬 Resolution</th>
                    <td>{{ $alert->close_comment }}</td>
                </tr>
                @endif
                <tr>
                    <th>🔗 Alert ID</th>
                    <td><code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;">#{{ $alert->alert_id }}</code></td>
                </tr>
            </table>

            {{-- Action Buttons --}}
            <div class="action-buttons">
                <a href="{{ $alertUrl }}" class="btn btn-primary">{{ $actionText['primary'] }}</a>
                <a href="{{ $dashboardUrl }}" class="btn btn-secondary">{{ $actionText['secondary'] }}</a>
            </div>

            {{-- Quick Actions Info --}}
            @if($type === 'new' && $alert->status === 'Active')
            <div style="background: #e3f2fd; padding: 15px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #2196f3;">
                <strong>🚀 Quick Actions Available:</strong><br>
                <small style="color: #1565c0;">
                    • Acknowledge this alert to mark it as seen<br>
                    • Close this alert with a resolution comment<br>
                    • View detailed metrics and trends
                </small>
            </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="email-footer">
            <p><strong>{{ $systemInfo['app_name'] }}</strong> - Monitoring System</p>
            <p>Generated on {{ $systemInfo['timestamp'] }}</p>
            <p>
                <a href="{{ $systemInfo['app_url'] }}">Visit Dashboard</a> |
                <a href="{{ $systemInfo['app_url'] }}/alerts">View All Alerts</a>
            </p>
            @if($systemInfo['environment'] !== 'production')
            <p style="color: #dc3545; font-weight: 600;">⚠️ {{ strtoupper($systemInfo['environment']) }} ENVIRONMENT</p>
            @endif
        </div>
    </div>
</body>
</html>