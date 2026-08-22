<?php

namespace App\Http\Controllers\Friends;

use App\Enums\FriendshipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Friends\StoreFriendshipRequest;
use App\Models\Friendship;
use App\Models\KrsPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FriendshipController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $accepted = Friendship::query()
            ->with(['requester', 'addressee'])
            ->where('status', FriendshipStatus::Accepted)
            ->where(function ($query) use ($user): void {
                $query->where('requester_id', $user->id)
                    ->orWhere('addressee_id', $user->id);
            })
            ->get()
            ->map(function (Friendship $friendship) use ($user) {
                $friend = $friendship->requester_id === $user->id
                    ? $friendship->addressee
                    : $friendship->requester;

                return [
                    'friendship_id' => $friendship->id,
                    'id' => $friend->id,
                    'name' => $friend->name,
                    'email' => $friend->email,
                ];
            })
            ->values();

        $incoming = Friendship::query()
            ->with('requester')
            ->where('addressee_id', $user->id)
            ->where('status', FriendshipStatus::Pending)
            ->get()
            ->map(fn (Friendship $friendship) => [
                'friendship_id' => $friendship->id,
                'id' => $friendship->requester->id,
                'name' => $friendship->requester->name,
                'email' => $friendship->requester->email,
            ])
            ->values();

        $outgoing = Friendship::query()
            ->with('addressee')
            ->where('requester_id', $user->id)
            ->where('status', FriendshipStatus::Pending)
            ->get()
            ->map(fn (Friendship $friendship) => [
                'friendship_id' => $friendship->id,
                'id' => $friendship->addressee->id,
                'name' => $friendship->addressee->name,
                'email' => $friendship->addressee->email,
            ])
            ->values();

        $sharedPlans = KrsPlan::query()
            ->with(['user', 'courseOffering'])
            ->where('is_shared_with_friends', true)
            ->where('user_id', '!=', $user->id)
            ->whereHas('user', function ($query) use ($accepted): void {
                $query->whereIn('id', $accepted->pluck('id'));
            })
            ->latest('updated_at')
            ->get()
            ->map(fn (KrsPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'owner_name' => $plan->user->name,
                'offering_id' => $plan->course_offering_id,
                'offering_title' => $plan->courseOffering->title,
            ])
            ->values();

        $myPlans = KrsPlan::query()
            ->with('courseOffering:id,title')
            ->withCount('items')
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->map(fn (KrsPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'offering_id' => $plan->course_offering_id,
                'offering_title' => $plan->courseOffering->title,
                'items_count' => $plan->items_count,
            ])
            ->values();

        return Inertia::render('friends/Index', [
            'friends' => $accepted,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'sharedPlans' => $sharedPlans,
            'myPlans' => $myPlans,
        ]);
    }

    public function store(StoreFriendshipRequest $request): RedirectResponse
    {
        $email = strtolower($request->string('email')->toString());
        $addressee = User::query()->whereRaw('lower(email) = ?', [$email])->firstOrFail();
        $requester = $request->user();

        abort_if($addressee->id === $requester->id, 422, 'Tidak bisa menambahkan diri sendiri.');

        $existing = Friendship::query()
            ->where(function ($query) use ($requester, $addressee): void {
                $query->where(function ($inner) use ($requester, $addressee): void {
                    $inner->where('requester_id', $requester->id)
                        ->where('addressee_id', $addressee->id);
                })->orWhere(function ($inner) use ($requester, $addressee): void {
                    $inner->where('requester_id', $addressee->id)
                        ->where('addressee_id', $requester->id);
                });
            })
            ->first();

        if ($existing !== null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Permintaan atau pertemanan sudah ada.',
            ]);

            return back();
        }

        Friendship::query()->create([
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => FriendshipStatus::Pending,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permintaan teman dikirim.']);

        return back();
    }

    public function accept(Request $request, Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->addressee_id === $request->user()->id, 403);
        abort_unless($friendship->status === FriendshipStatus::Pending, 422);

        $friendship->update(['status' => FriendshipStatus::Accepted]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permintaan teman diterima.']);

        return back();
    }

    public function decline(Request $request, Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->addressee_id === $request->user()->id, 403);
        abort_unless($friendship->status === FriendshipStatus::Pending, 422);

        $friendship->update(['status' => FriendshipStatus::Declined]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permintaan teman ditolak.']);

        return back();
    }

    public function destroy(Request $request, Friendship $friendship): RedirectResponse
    {
        $userId = $request->user()->id;

        abort_unless(
            in_array($userId, [$friendship->requester_id, $friendship->addressee_id], true),
            403,
        );

        $friendship->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pertemanan dihapus.']);

        return back();
    }
}
