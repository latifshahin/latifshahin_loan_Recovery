<?php

function no_contact_condition($days = 7) {
    return "
    (
        (SELECT MAX(ct.created_at)
         FROM contacts ct
         WHERE ct.customer_id = c.id) IS NULL

        OR 

        DATE(
            (SELECT MAX(ct.created_at)
             FROM contacts ct
             WHERE ct.customer_id = c.id)
        ) < DATE_SUB(CURDATE(), INTERVAL $days DAY)
    )
    ";
}