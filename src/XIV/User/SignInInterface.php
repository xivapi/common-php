<?php

namespace XIV\User;

interface SignInInterface
{
    public function getLoginAuthorizationUrl(): string;

    public function setLoginAuthorizationState(): \stdClass;

    public function getAuthorizationToken(): \stdClass;
}
