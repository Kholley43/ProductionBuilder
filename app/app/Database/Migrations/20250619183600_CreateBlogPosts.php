<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBlogPosts extends Migration
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
            'title' => [ 'type' => 'VARCHAR' ],
            'content' => [ 'type' => 'VARCHAR' ],
            'author' => [ 'type' => 'VARCHAR' ],
            'published_date' => [ 'type' => 'VARCHAR' ],
            'created_at' => [ 'type' => 'DATETIME', 'null' => true ],
            'updated_at' => [ 'type' => 'DATETIME', 'null' => true ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('blogposts');
    }

    public function down()
    {
        $this->forge->dropTable('blogposts');
    }
}