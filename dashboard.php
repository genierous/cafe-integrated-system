<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

switch ((int)currentRoleId()) {
    case ROLE_OWNER:
        redirect('modules/owner/dashboard.php');
        break;
    case ROLE_HR_MANAGER:
        redirect('modules/hr_manager/dashboard.php');
        break;
    case ROLE_HR_STAFF:
        redirect('modules/hr_staff/dashboard.php');
        break;
    case ROLE_EMPLOYEE_MANAGER:
        redirect('modules/employee_manager/dashboard.php');
        break;
    case ROLE_EMPLOYEE:
        redirect('modules/employee/dashboard.php');
        break;
    case ROLE_APPLICANT:
        redirect('modules/applicant/dashboard.php');
        break;
    case ROLE_CASHIER:
        redirect('pos/dashboard.php');
        break;
    case ROLE_INVENTORY_STAFF:
        redirect('modules/inventory/dashboard.php');
        break;
    case ROLE_FINANCE_STAFF:
        redirect('modules/finance/dashboard.php');
        break;
    default:
        logoutUser();
        redirect('index.php?error=unknown_role');
}
