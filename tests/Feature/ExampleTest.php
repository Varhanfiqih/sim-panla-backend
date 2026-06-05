<?php

test('the application redirects from root to admin', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});
