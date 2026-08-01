<?php

/**
 * HR: Testna zamjena opcionalnog Notification servisa u samostalnom Workspace testu.
 * EN: Test replacement for the optional Notification service in a standalone Workspace test.
 */

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleNotification\Service;

/**
 * HR: Minimalna oznaka opcionalnog Notification servisa za samostalni Workspace test.
 * EN: Minimal optional Notification-service marker for the standalone Workspace test.
 */
if (!class_exists(NotificationService::class)) {
    final class NotificationService
    {
    }
}
