<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

class ViewerAccessTest extends TestCase
{
    public function test_viewer_routes_are_not_registered_when_disabled(): void
    {
        // flow.viewer.enabled is off by default.
        $this->get('/flow')->assertNotFound();
    }
}
