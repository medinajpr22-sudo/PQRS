<?php

test('the central root redirects to the admin dashboard', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});
