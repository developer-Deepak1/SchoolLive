<?php
namespace SchoolLive\Controllers;

use SchoolLive\Models\UserModel;
use SchoolLive\Middleware\AuthMiddleware;
use SchoolLive\Core\BaseController;

class UsersController extends BaseController {
    private UserModel $users;

    public function __construct() {
        parent::__construct();
        $this->users = new UserModel();
    }

    public function list($params = []) {
        $current = $this->currentUser(); if (!$current) return;
        // Support simple filters: role_id, school_id, is_active
        $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;
        $schoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;
        $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : null;

        if ($roleId) {
            $data = $this->users->getUsersByRole($roleId);
            $this->ok('Users retrieved', $data);
            return;
        }

        if ($schoolId) {
            $data = $this->users->getUsersBySchool($schoolId);
            $this->ok('Users retrieved', $data);
            return;
        }

        if ($isActive !== null) {
            // Use getActiveUsers when is_active === 1
            if ($isActive === 1) {
                $data = $this->users->getActiveUsers();
                $this->ok('Users retrieved', $data);
                return;
            }
        }

        // Default: return all (without password)
        $data = $this->users->findAll();
        $this->ok('Users retrieved', $data);
    }

    public function get($params = []) {
        $id = $this->requireKey($params,'id','User ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $user = $this->users->findById($id);
        if (!$user) { $this->fail('User not found',404); return; }

        // Restrict access: allow same school or superadmin
        $currentRole = $current['role_id'] ?? $current['RoleID'] ?? null;
        $SUPER = defined('ROLE_SUPERADMIN') ? constant('ROLE_SUPERADMIN') : null;
        if ($SUPER === null) {
            // attempt to read roles file if present
            if (file_exists(__DIR__ . '/../config/roles.php')) {
                try { $r = require __DIR__ . '/../config/roles.php'; $SUPER = $r['ROLE_SUPERADMIN'] ?? $SUPER; } catch (\Throwable $_) {}
            }
        }

        if ($SUPER && intval($currentRole) === intval($SUPER)) {
            // allow
        } else {
            $userSchool = $user['SchoolID'] ?? $user['school_id'] ?? null;
            if ($userSchool && ($userSchool != ($current['school_id'] ?? null))) {
                $this->fail('Forbidden', 403); return;
            }
        }

        $this->ok('User retrieved', $user);
    }

    public function profile($params = []) {
        $current = $this->currentUser(); if(!$current) return;
        $id = $current['id'] ?? $current['user_id'] ?? $current['UserID'] ?? null;
        if (!$id) { $this->fail('User id missing',400); return; }
        $user = $this->users->findById($id);
        if (!$user) { $this->fail('User not found',404); return; }
        $this->ok('Profile retrieved', $user);
    }

    // Return only the current user's id (helper endpoint)
    public function getUserId($params = []) {
        $current = $this->currentUser(); if(!$current) return;
        $id = $current['id'] ?? $current['user_id'] ?? $current['UserID'] ?? null;
        if ($id === null) { $this->fail('User id missing', 400); return; }
        $this->ok('User id retrieved', ['id' => $id]);
    }

    public function create($params = []) {
        if (!$this->requireMethod('POST')) return;
        $current = $this->currentUser(); if(!$current) return;
        $input = $this->input();
        $input['created_by'] = $current['username'] ?? $current['user_name'] ?? 'System';
        $id = $this->users->createUser($input);
        if ($id) { $user = $this->users->findById($id); $this->ok('User created', $user, 201); } else { $this->fail('Failed to create user',500); }
    }

    public function update($params = []) {
        if (!$this->requireMethod('PUT')) return;
        $id = $this->requireKey($params,'id','User ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        $input = $this->input();
        $input['updated_by'] = $current['username'] ?? $current['user_name'] ?? 'System';
        $exists = $this->users->findById($id);
        if (!$exists) { $this->fail('User not found',404); return; }
        $ok = $this->users->updateUser($id, $input);
        if ($ok) { $user = $this->users->findById($id); $this->ok('User updated', $user); } else { $this->fail('Failed to update user',500); }
    }

    public function delete($params = []) {
        if (!$this->requireMethod('DELETE')) return;
        $id = $this->requireKey($params,'id','User ID'); if($id===null) return;
        $current = $this->currentUser(); if(!$current) return;
        // Soft-delete: mark inactive
        $ok = $this->users->updateUser($id, ['is_active' => 0, 'updated_by' => $current['username'] ?? 'System']);
        if ($ok) { $this->ok('User deleted'); } else { $this->fail('Failed to delete user',500); }
    }
}
