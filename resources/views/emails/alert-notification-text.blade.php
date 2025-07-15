{{-- Alert Notification Email Template (Plain Text) --}}
{{ strtoupper($systemInfo['app_name']) }} - ALERT NOTIFICATION
{{ str_repeat('=', 60) }}

@if($type === 'resolved')
✅ ALERT RESOLVED: {{ $alert->host->hostname }}
@elseif($type === 'test')
🧪 TEST NOTIFICATION
@elseif($alert->alert_level === 'Critical')
🔥 CRITICAL ALERT: {{ $alert->host->hostname }}
@elseif($alert->alert_level === 'Warning')
⚠️  WARNING ALERT: {{ $alert->host->hostname }}
@else
📊 ALERT: {{ $alert->host->hostname }}
@endif

{{ str_repeat('-', 60) }}

MESSAGE:
{{ $alert->alert_message }}

ALERT DETAILS:
{{ str_repeat('-', 30) }}
🖥️  Host:           {{ $alert->host->hostname }} ({{ $alert->host->ip_address }})
📊 Metric Type:    {{ $alert->metricType->type_name }}
@if($type !== 'resolved')
📈 Current Value:  {{ number_format($alert->current_value, 2) }} ⚠️ EXCEEDS THRESHOLD
🎯 Threshold:      {{ number_format($alert->threshold_value, 2) }}
@endif
📅 {{ $type === 'resolved' ? 'Resolved' : 'Detected' }}:      {{ $alert->created_at->format('Y-m-d H:i:s') }} ({{ $alert->created_at->diffForHumans() }})
🔗 Alert ID:       #{{ $alert->alert_id }}

@if($type === 'resolved' && $alert->status === 'Closed' && $alert->close_comment)
💬 RESOLUTION COMMENT:
{{ str_repeat('-', 30) }}
{{ $alert->close_comment }}

@endif
@if($type === 'new' && $alert->status === 'Active')
🚀 QUICK ACTIONS AVAILABLE:
{{ str_repeat('-', 30) }}
• Acknowledge this alert to mark it as seen
• Close this alert with a resolution comment  
• View detailed metrics and trends

@endif
DIRECT LINKS:
{{ str_repeat('-', 30) }}
📋 View Alert Details: {{ $alertUrl }}
🏠 Dashboard:          {{ $dashboardUrl }}
🌐 Main System:        {{ $systemInfo['app_url'] }}

SYSTEM INFORMATION:
{{ str_repeat('-', 30) }}
System:      {{ $systemInfo['app_name'] }}
Generated:   {{ $systemInfo['timestamp'] }}
Alert ID:    {{ $systemInfo['alert_id'] }}
@if($systemInfo['environment'] !== 'production')
Environment: {{ strtoupper($systemInfo['environment']) }} ⚠️
@endif

{{ str_repeat('=', 60) }}
This is an automated notification from {{ $systemInfo['app_name'] }}.
For support, please visit: {{ $systemInfo['app_url'] }}
{{ str_repeat('=', 60) }}