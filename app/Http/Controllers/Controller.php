<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="ZenMon API Documentation",
 *      description="Monitoring Application - Alternative to Zabbix/Nagios",
 *      @OA\Contact(
 *          email="admin@zenmon.local"
 *      ),
 *      @OA\License(
 *          name="MIT",
 *          url="http://opensource.org/licenses/MIT"
 *      )
 * )
 * 
 * @OA\Server(
 *      url="http://localhost:8001",
 *      description="ZenMon API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *      securityScheme="sanctum",
 *      type="apiKey",
 *      in="header",
 *      name="Authorization",
 *      description="Enter token in format (Bearer <token>)"
 * )
 */
abstract class Controller
{
    //
}