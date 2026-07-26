<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 *  Add per-user time display preference (UTC vs local time) column to the users table.
 */
class Migration_add_user_time_display extends CI_Migration {

    public function up()
    {
        if (!$this->db->field_exists('user_time_display', 'users')) {
            $fields = array(
                "user_time_display varchar(10) NOT NULL DEFAULT 'utc'"
            );
            $this->dbforge->add_column('users', $fields);
        }
    }

    public function down()
    {
        if ($this->db->field_exists('user_time_display', 'users')) {
            $this->dbforge->drop_column('users', 'user_time_display');
        }
    }
}
