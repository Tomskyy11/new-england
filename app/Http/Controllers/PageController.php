<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index()
    {
        return Inertia::render('Home', [
            'heroTitle' => 'Welcome to New England Clone',
            'description' => 'Modern redesign with high performance.'
        ]);
    }

    public function about()
    {
        return Inertia::render('About', [
            'content' => 'This is the about page of our modern redesign.'
        ]);
    }
}