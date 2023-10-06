<?php

namespace App\Http\Controllers;

use App\Models\Coworker;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Seeall;
use App\Models\Question;
use App\Models\Response;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\FileService;
use Illuminate\Support\Facades\Validator;
use Nette\Utils\Json;

class EditorController extends Controller
{
    public function __construct(protected FileService $fileService)
    {
    }
    public function index()
    {
        return Inertia::render('Frontend/See');
    }
    public function edit_index(Request $request)
    {
        // dd(123);
        // dd($request->user());
        $response = $request->user();
        return Inertia::render('Backend/Editorindex', ['response' => rtFormat($response)]);
    }
    public function edit_store(Request $request)
    {
        // 與當下時間
        $data = Carbon::now()->locale('zh-tw')->format('YmdHms');
        // 產生再轉成16進位亂瑪(15字元)
        $randomString = bin2hex(random_bytes(15));
        $combinedString = $data . $randomString;
        $user = $request->user();
        // dd($request->formText['qu_naires_title']);
        $request->validate([
            'formData.*.title' => 'required|min:0',
            'formData.*.type' => 'required|numeric',
            'formText.qu_naires_title' => 'required|string',
        ], [
            'formData.*.title.required' => '問題:position必填 ',
            'formText.qu_naires_title.required' => '表單標題必填',
        ]);
        // :position 第幾個意思
        // dd(json_encode($request->formData, JSON_UNESCAPED_UNICODE));
        // 處理圖片
        foreach ($request->formData as $item) {
            $item['image'] = $this->fileService->base64Upload($request->image, 'editor');
        }
        $jsonText = json_encode($request->formData, JSON_UNESCAPED_UNICODE);
        $this->fileService->base64Upload($request->image, 'editor');
        $textData = Question::create([
            'qu_naires_title' => $request->formText['qu_naires_title'],
            'qu_naires_desc' => $request->formText['qu_naires_desc'],
            'questionnaires' => $jsonText,
            'lead_author_id' => $user->id,
            'random' => $combinedString,
        ]);
        return back()->with(['message' => rtFormat($combinedString)]);
    }
    public function edit_old(Request $request)
    {
        // dd(session()->all());
        // dd(session()->get('update_token'));

        // 先找到該用戶自己的表單，再找到指定id的表單，避免猜網址
        // $responseForm = Question::where('lead_author_id', $request->user()->id)
        //     ->orWhereHas('coworker', fn ($coQuery) => $coQuery->where('coworker_id', $request->user()->id))
        //     ->find($request->id);
        $responseForm = Question::where(function ($query) use ($request) {
            return $query->where('lead_author_id', $request->user()->id)
                ->orWhereHas('coworker', function ($coQuery) use ($request) {
                    return $coQuery->where('coworker_id', $request->user()->id);
                });
        })->find($request->id);
        // dd($responseForm);


        if (!session()->get('update_token') == $request->id) {
            $responseForm->update([
                'opened_date' => Carbon::now(),
            ]);
        }

        session()->forget('update_token');

        // 將找到的問卷裡面，題目那一欄(當時存成json)，解開
        $questionNaires = json_decode($responseForm['questionnaires'], true);
        // dd($questionNaires);
        $response = [
            'responseForm' => $responseForm,
            'questionNaires' => $questionNaires,
        ];

        return Inertia::render('Backend/Editorold', ['response' => rtFormat($response)]);
    }
    public function edit_update(Request $request)
    {

        $request->validate([
            'formData.*.title' => 'required|string',
            'formData.*.type' => 'required|numeric',
            'formText.qu_naires_title' => 'required|string',
        ], [
            'formData.*.title.required' => '問題:position必填 ',
            'formText.qu_naires_title.required' => '表單標題必填',
        ]);
        // dd(json_encode($request->formData, JSON_UNESCAPED_UNICODE));

        $jsonText = json_encode($request->formData, JSON_UNESCAPED_UNICODE);

        // 找到表單id
        // dd($request->formText['id']);
        // dd($request->formData);
        $updateForm = Question::where('lead_author_id', $request->user()->id)->find($request->formText['id']);
        // 處理圖片
        foreach ($request->formData as $item) {
            $item['image'] = $this->fileService->base64Upload($request->image, 'editor');
        }
        // dd($updateForm);
        $updateForm->update([
            'qu_naires_title' => $request->formText['qu_naires_title'],
            'qu_naires_desc' => $request->formText['qu_naires_desc'],
            'questionnaires' => $jsonText,
            'modified_at' => Carbon::now(),
        ]);
        // dd(132);
        return back()->with(['message' => rtFormat($updateForm), 'update_token' => $request->formText['id']]);
    }
    public function edit_delete(Request $request)
    {
        // dd($request->id);
        $formDel = Question::find($request->id);
        // dd( $formDel );
        $formDel->delete();
        return back()->with(['message' => rtFormat($formDel)]);
    }
    public function edit_rename(Request $request)
    {
        // 這段驗證不知道為甚麼沒效
        // $request->validate([
        //     'modalData.newName => required|string',
        // ], [
        //     'modalData.newName' => '表單標題必填',
        // ]);

        $updateForm = Question::find($request->modalData['id']);
        // dd($updateForm);
        $updateForm->update([
            'qu_naires_title' => $request->modalData['newName']
        ]);
        // dd(132);
        return back()->with(['message' => rtFormat($updateForm)]);
    }

    public function coformid_index(Request $request)
    {

        // 利用關聯user找到 user=>id
        $ownerids = Question::with('user')->where('id', $request->coFormId)->first();
        $ownerid = $ownerids->user;
        // 利用關聯user找到 user=>id
        $formIds = Coworker::with('user')->where('question_id', $request->coFormId)->get();
        return back()->with(['message' => rtFormat([$formIds, $ownerid])]);
    }

    public function coformid_store(Request $request)
    {
        $user = User::select('email', 'id')->where('email', $request->co_email)->first();
        // 登入帳號不能填入自己email
        if ($request->user()->email === $request->co_email) {
            return redirect()->route('edit.old', ['id' => $request->coFormIdNumber])->with(['message' => rtFormat($user, 0, '已擁有編輯使用者')]);
        }
        $repeatid = Coworker::where('coworker_id', $user->id)->get();
        // 找到此表單有一樣email就跳出
        foreach ($repeatid as $index) {
            if ($index->question_id === $request->coFormIdNumber) {
                return redirect()->route('edit.old', ['id' => $request->coFormIdNumber])->with(['message' => rtFormat($user, 0, '已擁有編輯使用者')]);
            }
        }
        // 找不到email跳出
        if (!$user) {
            return redirect()->route('edit.old', ['id' => $request->coFormIdNumber])->with(['message' => rtFormat($user, 0, '查無資料')]);
        }
        $formId = Coworker::create([
            'question_id' => $request->coFormIdNumber,
            'coworker_id' => $user->id,
        ]);
        return back()->with(['message' => rtFormat($formId)]);
    }
}
