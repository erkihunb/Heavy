<?php

namespace App\Http\Requests\Staff;

class StartReviewRequest extends PermitActionRequest
{
    protected function ability(): string
    {
        return 'startReview';
    }
}
