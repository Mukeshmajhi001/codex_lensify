<?php

function ensure_address_fields(): void
{
    static $ready = false;
    if ($ready || !db_available()) {
        return;
    }

    $fields = [
        'district' => 'VARCHAR(100) NULL',
        'municipality' => 'VARCHAR(140) NULL',
        'ward_number' => 'VARCHAR(20) NULL',
        'tole_locality' => 'VARCHAR(140) NULL',
        'street_chowk' => 'VARCHAR(140) NULL',
        'house_number' => 'VARCHAR(80) NULL',
        'nearby_landmark' => 'VARCHAR(190) NULL',
    ];
    $columns = db()->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'addresses'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($fields as $name => $definition) {
        if (!in_array($name, $columns, true)) {
            db()->exec("ALTER TABLE addresses ADD COLUMN {$name} {$definition}");
        }
    }
    $ready = true;
}
