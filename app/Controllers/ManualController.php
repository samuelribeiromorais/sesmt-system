<?php

namespace App\Controllers;

use App\Core\Controller;

class ManualController extends Controller
{
    public function index(): void
    {
        $this->view('manual/index', [], '');
    }
}
