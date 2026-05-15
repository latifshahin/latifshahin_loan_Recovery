<?php
function currentRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : '';
}

function roleIds() {
    return [
        'branch_id' => isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : 0,
        'zone_id' => isset($_SESSION['zone_id']) ? intval($_SESSION['zone_id']) : 0,
        'circle_id' => isset($_SESSION['circle_id']) ? intval($_SESSION['circle_id']) : 0,
        'officer_id' => isset($_SESSION['officer_id']) ? intval($_SESSION['officer_id']) : 0,
    ];
}

function customerScopeSql($alias = 'c') {
    $role = currentRole();
    $ids = roleIds();

    if ($role === 'ho_admin') {
        return '1=1';
    }
    if ($role === 'circle_admin') {
        return "z.circle_id = " . $ids['circle_id'];
    }
    if ($role === 'zone_admin') {
        return "b.zone_id = " . $ids['zone_id'];
    }
    if ($role === 'admin') {
        return "$alias.branch_id = " . $ids['branch_id'];
    }
    return "$alias.branch_id = " . $ids['branch_id'] . " AND $alias.assigned_officer = " . $ids['officer_id'];
}

function canAccessCustomer($conn, $customer_id) {
    $customer_id = intval($customer_id);
    $scope = customerScopeSql('c');
    $sql = "SELECT c.id
            FROM customers c
            LEFT JOIN branches b ON c.branch_id = b.id
            LEFT JOIN zones z ON b.zone_id = z.id
            WHERE c.id = $customer_id AND $scope
            LIMIT 1";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0);
}

function scopedCustomers($conn) {
    $scope = customerScopeSql('c');
    $sql = "SELECT c.id, c.name, c.account_number, c.assigned_officer
            FROM customers c
            LEFT JOIN branches b ON c.branch_id = b.id
            LEFT JOIN zones z ON b.zone_id = z.id
            WHERE $scope
            ORDER BY c.name ASC";
    return $conn->query($sql);
}

function scopedOfficers($conn, $customer_id = 0) {
    $role = currentRole();
    $ids = roleIds();
    $customer_id = intval($customer_id);

    if ($role === 'ho_admin') {
        $where = "o.status='Active'";
    } elseif ($role === 'circle_admin') {
        $where = "o.status='Active' AND z.circle_id = " . $ids['circle_id'];
    } elseif ($role === 'zone_admin') {
        $where = "o.status='Active' AND b.zone_id = " . $ids['zone_id'];
    } elseif ($role === 'admin') {
        $where = "o.status='Active' AND o.branch_id = " . $ids['branch_id'];
    } else {
        $where = "o.status='Active' AND o.id = " . $ids['officer_id'];
    }

    if ($customer_id > 0) {
        $where .= " AND o.branch_id = (SELECT branch_id FROM customers WHERE id = $customer_id)";
    }

    $sql = "SELECT o.id, o.name, o.branch_id
            FROM officers o
            LEFT JOIN branches b ON o.branch_id = b.id
            LEFT JOIN zones z ON b.zone_id = z.id
            WHERE $where
            ORDER BY o.name ASC";
    return $conn->query($sql);
}

function getScopedCustomer($conn, $customer_id) {
    $customer_id = intval($customer_id);
    $scope = customerScopeSql('c');
    $sql = "SELECT c.*, o.name AS officer_name
            FROM customers c
            LEFT JOIN officers o ON c.assigned_officer = o.id
            LEFT JOIN branches b ON c.branch_id = b.id
            LEFT JOIN zones z ON b.zone_id = z.id
            WHERE c.id = $customer_id AND $scope
            LIMIT 1";
    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : null;
}
?>
