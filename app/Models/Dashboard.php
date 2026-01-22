<?php

namespace App\Models;

/**
 * Compatibility model for legacy "dashboards" routes.
 *
 * This project originally generated a Dashboard resource route, but we don't
 * persist dashboards separately. Treat a "dashboard" as a Sprint row.
 *
 * If you remove the dashboards routes, you can delete this file + the
 * DashboardController.
 */
class Dashboard extends Sprint
{
    protected $table = 'sprints';
}
