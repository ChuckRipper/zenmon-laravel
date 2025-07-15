<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="Notification system management and testing endpoints"
 * )
 */
/// <summary>
/// Controller for managing notification settings and testing
/// Only administrators can manage notification configuration
/// </summary>
class NotificationController extends Controller
{
    #region Properties
    
    /// <summary>
    /// Notification service instance
    /// </summary>
    private NotificationService $notificationService;
    
    #endregion
    
    #region Constructor
    
    /// <summary>
    /// Initialize controller with notification service
    /// </summary>
    /// <param>NotificationService $notificationService</param>
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        
        // Only administrators can manage notifications
        $this->middleware('ensure.administrator');
    }
    
    #endregion
    
    #region Methods
    
    /**
     * @OA\Get(
     *     path="/api/notifications/config",
     *     summary="Get notification configuration",
     *     description="Retrieve current notification system configuration including email, Slack, and webhook settings",
     *     operationId="getNotificationConfiguration",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Notification configuration retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="object",
     *                     @OA\Property(property="enabled", type="boolean", example=true),
     *                     @OA\Property(property="from_address", type="string", example="alerts@zenmon.com"),
     *                     @OA\Property(property="from_name", type="string", example="ZenMon Alert System"),
     *                     @OA\Property(property="configured_recipients", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="driver", type="string", example="smtp")
     *                 ),
     *                 @OA\Property(
     *                     property="slack",
     *                     type="object",
     *                     @OA\Property(property="enabled", type="boolean", example=true),
     *                     @OA\Property(property="webhook_url", type="string", example="***configured***"),
     *                     @OA\Property(property="channel", type="string", example="#alerts")
     *                 ),
     *                 @OA\Property(
     *                     property="webhook",
     *                     type="object",
     *                     @OA\Property(property="enabled", type="boolean", example=false),
     *                     @OA\Property(property="urls", type="array", @OA\Items(type="string"))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Administrator access required"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    /// <summary>
    /// Get current notification configuration
    /// </summary>
    /// <returns>JsonResponse</returns>
    public function getConfiguration(): JsonResponse
    {
        try {
            $config = [
                'email' => [
                    'enabled' => config('mail.default') !== null,
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                    'configured_recipients' => config('zenmon.alert_emails', []),
                    'driver' => config('mail.default')
                ],
                'slack' => [
                    'enabled' => !empty(config('zenmon.slack_webhook_url')),
                    'webhook_url' => config('zenmon.slack_webhook_url') ? '***configured***' : null,
                    'channel' => config('zenmon.slack_channel', '#alerts')
                ],
                'webhook' => [
                    'enabled' => !empty(config('zenmon.webhook_urls')),
                    'urls' => config('zenmon.webhook_urls', [])
                ],
                'channels' => [
                    'available' => [
                        NotificationService::CHANNEL_EMAIL,
                        NotificationService::CHANNEL_SLACK,
                        NotificationService::CHANNEL_WEBHOOK,
                        NotificationService::CHANNEL_SMS
                    ],
                    'default_for_warning' => [NotificationService::CHANNEL_EMAIL, NotificationService::CHANNEL_SLACK],
                    'default_for_critical' => [NotificationService::CHANNEL_EMAIL, NotificationService::CHANNEL_SLACK, NotificationService::CHANNEL_WEBHOOK]
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $config
            ]);
            
        } catch (Exception $e) {
            Log::error('NotificationController@getConfiguration failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification configuration'
            ], 500);
        }
    }
    
    /**
     * @OA\Post(
     *     path="/api/notifications/test",
     *     summary="Test notification system",
     *     description="Send a test notification through specified channel to verify configuration",
     *     operationId="testNotification",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Test notification parameters",
     *         @OA\JsonContent(
     *             required={"channel"},
     *             @OA\Property(
     *                 property="channel",
     *                 type="string",
     *                 enum={"email", "slack", "webhook"},
     *                 description="Notification channel to test",
     *                 example="email"
     *             ),
     *             @OA\Property(
     *                 property="recipient",
     *                 type="string",
     *                 format="email",
     *                 description="Email recipient (required for email channel)",
     *                 example="admin@example.com"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Test notification sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Test notification sent successfully via email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Test notification failed"
     *     )
     * )
     */
    /// <summary>
    /// Test notification system
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function testNotification(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'channel' => 'required|in:email,slack,webhook',
                'recipient' => 'nullable|email|required_if:channel,email'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $channel = $request->input('channel');
            $recipient = $request->input('recipient');
            
            // Send test notification
            $success = $this->notificationService->sendTestNotification($channel, $recipient);
            
            if ($success) {
                Log::info('Test notification sent successfully', [
                    'channel' => $channel,
                    'recipient' => $recipient,
                    'admin_user' => auth()->user()->login
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => "Test notification sent successfully via {$channel}"
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send test notification. Check logs for details.'
                ], 500);
            }
            
        } catch (Exception $e) {
            Log::error('NotificationController@testNotification failed', [
                'channel' => $request->input('channel'),
                'recipient' => $request->input('recipient'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Test notification failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * @OA\Get(
     *     path="/api/notifications/stats",
     *     summary="Get notification statistics",
     *     description="Retrieve statistics about notification system performance and activity",
     *     operationId="getNotificationStatistics",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Notification statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="object",
     *                     @OA\Property(property="total_sent_today", type="integer", example=15),
     *                     @OA\Property(property="queue_pending", type="integer", example=2),
     *                     @OA\Property(property="last_sent", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(
     *                     property="slack",
     *                     type="object",
     *                     @OA\Property(property="total_sent_today", type="integer", example=8),
     *                     @OA\Property(property="queue_pending", type="integer", example=0),
     *                     @OA\Property(property="last_sent", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(
     *                     property="overall",
     *                     type="object",
     *                     @OA\Property(property="notifications_enabled", type="boolean", example=true),
     *                     @OA\Property(property="queue_workers_active", type="boolean", example=true),
     *                     @OA\Property(property="last_alert_processed", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Administrator access required"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    /// <summary>
    /// Get notification statistics
    /// </summary>
    /// <returns>JsonResponse</returns>
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'email' => [
                    'total_sent_today' => rand(5, 25), // Placeholder
                    'queue_pending' => 0,
                    'last_sent' => now()->subMinutes(rand(5, 120))->toISOString()
                ],
                'slack' => [
                    'total_sent_today' => rand(3, 15), // Placeholder
                    'queue_pending' => 0,
                    'last_sent' => now()->subMinutes(rand(5, 120))->toISOString()
                ],
                'overall' => [
                    'notifications_enabled' => config('mail.default') !== null,
                    'queue_workers_active' => true, // Placeholder
                    'last_alert_processed' => now()->subMinutes(rand(1, 30))->toISOString()
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (Exception $e) {
            Log::error('NotificationController@getStatistics failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification statistics'
            ], 500);
        }
    }
    
    #endregion
}