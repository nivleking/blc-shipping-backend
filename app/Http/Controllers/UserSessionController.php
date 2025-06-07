<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserSessionRequest;
use App\Http\Requests\UpdateUserSessionRequest;
use App\Models\UserSession;

class UserSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserSessionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(UserSession $userSession)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserSessionRequest $request, UserSession $userSession)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UserSession $userSession)
    {
        //
    }
}
