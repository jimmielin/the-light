<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\Message as MessageResource;

class MessagesController extends ApiController
{
    /**
     * @group System Messages
     *
     * View System Messages
     * Lists system messages received by user. Only shows latest 25 by design.
     *
     * @queryParam user_token required
     *
     */
    public function view(Request $request) {
        return MessageResource::collection($request->uData->messages()->latest()->limit(25)->get());
    }

}
