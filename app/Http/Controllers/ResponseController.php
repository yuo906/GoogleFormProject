<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Response;
use App\Models\Question;
use App\Models\User;

class ResponseController extends Controller
{
    public function response_sum()
    {
        // 找到對應的問券
        $responseForm = Question::where('id', 20)->first();
        // $questionNaires是問卷的題目內容
        $questionNaires = json_decode($responseForm['questionnaires'], true);

        // 找到對應該份問卷的所有回覆
        $datas = Response::where('question_id', 20)->get();
        //$results會裝取出來的回覆資料
        $results = [];
        foreach ($datas as $data) {
            $answer = json_decode($data['answer'], true);
            $results[] = $answer;
        }
        // 找到回覆問卷的使用者id
        $whoRespond = [];
        foreach ($datas as $data) {
            $whoRe = $data['user_id'];
            $whoRespond[] = $whoRe;
        }
          // 根據回覆問卷的使用者id，在USER表找註冊時的信箱，此寫法缺點是預設使用者都同意蒐集信箱，所以response表沒開存信箱的欄位，直接從user表找。
        $whoAll = [];
        foreach ($whoRespond as $item) {
            $whoIn =User::where('id',$item)->get();
            $whoAll[] =$whoIn[0]['email'];
        }


        // 回覆和題目內容一起打包給前台
        $response = [
            'results' => $results,
            'questionNaires' => $questionNaires,
            'whoAll'=>$whoAll,
        ];

        return Inertia::render('Backend/ResponseSum', ['response' => rtFormat($response)]);
    }
    public function responseQue(Request $request, $id)
    {   //dd($id);
        $datas = Response::where('question_id', $id)->get();
        $results = [];
        foreach ($datas as $data) {
            $answer = json_decode($data['answer'], true);
            $results[] = $answer;
        }
        $response = [
            'results' => $results,
        ];
        return Inertia::render('Backend/ResponseQue',['response' => rtFormat($response)]);
    }
}
