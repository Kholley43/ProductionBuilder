<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateACustomers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 9,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [ 'type' => 'VARCHAR' ],
            'email' => [ 'type' => 'VARCHAR' ],
            'phone' => [ 'type' => 'VARCHAR' ],
            'address' => [ 'type' => 'VARCHAR' ],
            'created_at' => [ 'type' => 'DATETIME', 'null' => true ],
            'updated_at' => [ 'type' => 'DATETIME', 'null' => true ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('acustomers');
    }

    public function down()
    {
        $this->forge->dropTable('acustomers');
    }
}