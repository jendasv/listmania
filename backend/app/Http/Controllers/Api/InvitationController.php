<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\HouseholdRole;
use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Mail\HouseholdInvitation;
use App\Models\Invitation;
use App\Models\Liste;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $this->authorize('send', Invitation::class);

        $user = $request->user();
        $household = $user->households()->wherePivot('role', HouseholdRole::Owner->value)->first();

        if (Str::lower($request->email) === Str::lower($user->email)) {
            return response()->json(['message' => 'You cannot invite yourself.'], 422);
        }

        // Unknown and known addresses get the exact same response — anything
        // else lets any owner probe which email addresses have an account.
        $invitee = User::where('email', $request->email)->first();
        if (! $invitee) {
            return $this->invitationSentResponse();
        }

        $alreadyMember = $household->members()->where('users.id', $invitee->id)->exists();
        if ($alreadyMember) {
            return response()->json(['message' => 'User is already a member of this household.'], 422);
        }

        Invitation::where('household_id', $household->id)
            ->where('email', $request->email)
            ->where('status', InvitationStatus::Pending->value)
            ->update(['status' => InvitationStatus::Expired->value]);

        $invitation = Invitation::create([
            'household_id' => $household->id,
            'invited_by' => $user->id,
            'email' => $request->email,
            'token' => Str::random(64),
            'status' => InvitationStatus::Pending->value,
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load(['household', 'invitedBy']);
        // Sent after the response is flushed: an SMTP round-trip only on the
        // known-address path would make response time reveal what the
        // identical response body hides.
        dispatch(fn () => Mail::to($invitee)->send(new HouseholdInvitation($invitation)))->afterResponse();

        return $this->invitationSentResponse();
    }

    private function invitationSentResponse(): JsonResponse
    {
        return response()->json(['message' => 'If an account exists for this email, an invitation has been sent.'], 202);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isPending()) {
            return response()->json(['message' => 'Invalid or expired invitation.'], 404);
        }

        $this->authorize('accept', $invitation);

        $user = $request->user();
        $targetHousehold = $invitation->household;
        $ownHousehold = $user->households()->wherePivot('role', HouseholdRole::Owner->value)->first();

        DB::transaction(function () use ($user, $targetHousehold, $ownHousehold) {
            if ($ownHousehold) {
                // Only lists the user created themselves move with them —
                // not every list in their own household (other members may have some).
                Liste::where('household_id', $ownHousehold->id)
                    ->where('created_by', $user->id)
                    ->update(['household_id' => $targetHousehold->id]);
            }

            if (! $targetHousehold->members()->wherePivot('user_id', $user->id)->exists()) {
                $targetHousehold->members()->attach($user->id, ['role' => HouseholdRole::Member->value]);
            }

            $user->setCurrentHousehold($targetHousehold->id);
        });

        $invitation->update(['status' => InvitationStatus::Accepted->value]);

        return response()->json(['message' => 'Invitation accepted.']);
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isPending()) {
            return response()->json(['message' => 'Invalid or expired invitation.'], 404);
        }

        $this->authorize('decline', $invitation);

        $invitation->update(['status' => InvitationStatus::Declined->value]);

        return response()->json(['message' => 'Invitation declined.']);
    }
}
