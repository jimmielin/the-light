<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class HoleCoreTest extends TestCase
{

    /**
     * Test GetList and basic READ features.
     *
     * @return void
     */
    public function testGetList()
    {
        $response = $this->get('/api/holes/list?user_token=3bdhxtbdob7cnoJeJGFH8I8VLNKysPUIFJ');

        $response->assertStatus(200)->assertJson([
            "success" => true
        ]);
    }

    /**
     * Test reading one hole
     *
     * @return void
     */
    public function testGetPost()
    {
        $response = $this->get('/api/holes/view/1?user_token=3bdhxtbdob7cnoJeJGFH8I8VLNKysPUIFJ');

        $response->assertStatus(200)->assertJson([
            "success" => true,
            "post_data" => [
                "pid" => 1
            ]
        ]);
    }

    /**
     * Test posting one hole and making a reply. The data will be rolled back
     * internally.
     * @return void
     */
    public function testPost()
    {
        $response = $this->post('/api/holes/post?user_token=3bdhxtbdob7cnoJeJGFH8I8VLNKysPUIFJ', [
            "type" => "text",
            "text" => "This is a testing post made from automatic unit tests."
        ]);

        $response->assertStatus(200)->assertJson([
            "success" => true
        ]);

        $pid = $response["data"];
        $response = $this->post('/api/holes/reply/' . $pid . '?user_token=3bdhxtbdob7cnoJeJGFH8I8VLNKysPUIFJ', [
            "type" => "text",
            "text" => "This is an automatic reply made at " . time()
        ]);


    }
}
