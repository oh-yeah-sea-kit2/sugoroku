<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\UserRepository;
use App\Repositories\RoomRepository;
use App\Http\Requests\StoreRoomRequest;

class RoomController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $roomRepository = new RoomRepository();
        $userRepository = new UserRepository();

        $rooms = $roomRepository->getOpenRooms();

        foreach ($rooms as $room) {
            // 有効な部屋に入室済みの場合
            if ($roomRepository->isMember($room, Auth::id(), $room->id)) {
                return redirect()->route('room.show', ['uname' => $room->uname]);
            }
        }
        $pusher_token = $userRepository->getPersonalAccessToken();

        return view('room.index', compact('rooms', 'pusher_token'));
    }

    public function store(StoreRoomRequest $request)
    {
        $repository = new RoomRepository();

        // オープン中の部屋を既に所有していたらエラー
        $room = $repository->getOwnOpenRoom(Auth::id());
        if ($room) {
            return response()->json([
                'status'    => 'error',
                'message'   => '既にオープン中の部屋があるようです'
            ]);
        }

        // 現在の有効な部屋数が有効な部屋数以上であればエラー
        $currentActiveRoomsCount = $repository->getCurrentActiveRoomsCount();
        if ($currentActiveRoomsCount >= config('const.max_number_of_active_rooms')) {
            return response()->json([
                'status'    => 'error',
                'message'   => '現在の有効部屋数が有効部屋数を超えているようです'
            ]);
        }

        // 部屋作成
        $validated = $request->validated();
        $data = [
            'name'      => $validated['name'],
            'owner_id'  => Auth::id(),
            'board_id'  => 1
        ];
        $roomId = $repository->create($data);

        // 入室処理
        $repository->addMember(Auth::id(), $roomId);

        return response()->json([
            'status'     => 'success'
        ]);
    }

    public function show($uname)
    {
        $roomRepository = new RoomRepository();
        $userRepository = new UserRepository;

        $room = $roomRepository->findByUname($uname);
        if ($room === null) {
            return abort(404);
        }
        if (!$roomRepository->isMember($room, Auth::id(), $room->id)) {
            return abort(404);
        }

        $spaces = $roomRepository->getSpaces($room);

        $pusher_token = $userRepository->getPersonalAccessToken();

        return view('room.show', compact('room', 'spaces', 'pusher_token'));
    }
    
    public function join($uname) {
        $repository = new RoomRepository();
        $room = $repository->findByUname($uname);

        if ($room === null) {
            abort(404);
        }

        if ($repository->isMember($room, Auth::id(), $room->id)) {
            return redirect('/room/'.$uname);
        }

        if($repository->addMember(Auth::id(), $room->id)) {
            return redirect('/room/'.$uname);

        } else {
            return response()->json([
                'status'    => 'error',
                'message'   => '入室できませんでした'
            ]);
        }
    }

}
