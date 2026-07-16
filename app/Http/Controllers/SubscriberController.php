<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Subscriber::firstOrCreate(['email' => $data['email']], ['is_active' => true]);

        return back()->with('subscribe_status', 'Thank you! You have been subscribed.');
    }
}
