<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\MessageSent;
use App\Models\Message;

class ChatController extends Controller
{
    public function index()
    {
        return response()->json(Message::with('user')->get()); // Ensure 'user' relation exists
    }

    public function sendMessage(Request $request)
    {

        $request->validate([
            'message' => 'required|string',
        ]);

        $message = Message::create([
            'user_id' => auth()->id(),
            'message' => $request->message,
        ]);

        // Broadcast the message
        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message);
    }
}

