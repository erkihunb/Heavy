<?php

namespace App\Actions\Permits;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\Permit;
use App\Models\PermitComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommentOnPermit
{
    public function __construct(private readonly AuditLog $audit) {}

    /**
     * The head office leaves a comment on an application. Staff handling it
     * see the comment; the applicant never does.
     */
    public function handle(Permit $permit, User $author, string $body): PermitComment
    {
        return DB::transaction(function () use ($permit, $author, $body): PermitComment {
            $comment = new PermitComment(['body' => trim($body)]);
            $comment->permit_id = $permit->id;
            $comment->user_id = $author->id;
            $comment->save();

            $this->audit->record(AuditEvent::PermitCommented, $permit, $author,
                context: array_filter(['permit_number' => $permit->permit_number, 'comment_id' => $comment->id]),
            );

            return $comment;
        });
    }
}
