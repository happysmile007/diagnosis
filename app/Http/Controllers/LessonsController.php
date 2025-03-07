<?php

namespace App\Http\Controllers;

use App\Helpers\Auth\Auth;
use App\Mail\Frontend\LiveLesson\StudentMeetingSlotMail;
use App\Models\Lesson;
use App\Models\LessonSlotBooking;
use App\Models\LiveLessonSlot;
use App\Models\Media;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Question;
use App\Models\QuestionsOption;
use App\Models\Test;
use App\Models\TestsResult;
use App\Models\TextGroup;
use App\Models\VideoProgress;
use Illuminate\Http\Request;
use DB;
use Facade\Ignition\Tabs\Tab;
use Faker\Provider\Barcode;
use \Schema;
use App\Mail\SendEmail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class LessonsController extends Controller
{
    private $path;

    public function __construct()
    {
        $path = 'frontend';
        // return $path; 
        
        if (session()->has('display_type')) {
            if (session('display_type') == 'rtl') {
                $path = 'frontend-rtl';
            } else {
                $path = 'frontend';
            }
        } else if (config('app.display_type') == 'rtl') {
            $path = 'frontend-rtl';
        }
        $this->path = $path;
    }

    public function updateprofilepic(Request  $request){
            $user_id = auth()->user()->id;
            $question_id= $request->post('question_id');
                
            if($_FILES["fileUpload"]["name"] != '')
            {
                $test = explode('.', $_FILES["fileUpload"]["name"]);
                $ext = end($test);
                $name = rand(100, 999) . '.' . $ext;
                $location = public_path('/assets/img/').$name;
                move_uploaded_file($_FILES["fileUpload"]["tmp_name"], $location);
                //$request->image->move(public_path('/assets/img'), $name);

                $data = [
                'answer' => $name,
                ];
                
                $question_data = DB::table('questions')
                ->where('id',$question_id)      
                ->first();
            $score = json_decode($question_data->score);

            if ($question_data->questiontype ==1 || $question_data->questiontype == 2 || $question_data->questiontype == 3 || $question_data->questiontype ==7)
            {
                $k =0; $score_temp=0;
                
            }
            else if ($question_data->questiontype == 0)
            {
                $score_temp =$score;
            }

            else if ($question_data->questiontype == 4)
            {
                $score_temp = $score[1][1];
            }

            $ii=DB::table('user_answer')
            ->where('question_id',$question_id)
            ->where('user_id',$user_id)->count();
            if ($ii ==0)
            {
                DB::table('user_answer')
                ->insert([
                        'question_id' => $question_id,
                        'answer' => json_encode($data),
                        'user_id' =>$user_id ,
                        'score' => $score_temp
                ]);
            }
                //echo 'true';
             echo '<img src="'.url('/assets/img/').'/'.$name.'" height="150" width="150" class="responsive-img" id="proimage" alt="profile"/>';
            
        }else{
            echo 'false';
        }

        //redirect( $this->agent->referrer());
    }

    public function store_answer(Request $request)
    {
        
        $user_id = auth()->user()->id;
        $answer_data = json_decode($request->data['answer']);
        $test_id = $request->data['test_id'];

        $len = count($answer_data);
        $score_temp = 0;

        for ($i =0;$i<$len;$i++)
        {
            $question_id= $request->data['question_id'][$i];

            $question_data = DB::table('questions')
                ->where('id',$question_id)
                ->get()->toArray();

            $score = json_decode($question_data[0]->score);

            if ($question_data[0]->questiontype ==1 || $question_data[0]->questiontype == 2 || $question_data[0]->questiontype == 3)
            {
                $k =0; $score_temp=0;
                $answer_temp = $answer_data[$i];
                while($answer_temp/2 !=0)
                {
                    $k++;
                    if (is_array($score))
                        $score_temp += ($answer_temp % 2) * floatval($score[count($score)-$k]);
                    else 
                        $score_temp += ($answer_temp % 2) * floatval($score);
                    $answer_temp = ( $answer_temp - $answer_temp % 2)/2; 
                }
            }
            else if ($question_data[0]->questiontype == 0)
            {
                $score_temp =$score;
            }

            else if ($question_data[0]->questiontype == 4)
            {
                $score_temp = $score;
            }

            $ii=DB::table('user_answer')
                ->where('question_id',$request->data['question_id'][$i])
                ->where('user_id',$user_id)->count();
            
            if ($ii ==0)
            {
                DB::table('user_answer')
                    ->insert([
                        'question_id' => $request->data['question_id'][$i],
                        'answer' => json_encode($answer_data[$i]),
                        'user_id' =>$user_id,
                        'score' => $score_temp
                    ]);
            }

            else
            {
                DB::table('user_answer')
                ->where('question_id',$request->data['question_id'][$i])
                ->where('user_id',$user_id)
                ->where('test_id',$test_id)
                ->update([                        // 'question_id' => $request->data['question_id'][$i],
                         'answer' =>json_encode($answer_data[$i]),
                         'score'=>$score_temp
                        //'user_id' =>$user_id 
                ]);
            }
           
        }

        $tid= DB::table('textgroup_test')->select('text_id')->where("test_id", $request->data['test_id'])->get()->toArray();
       
        $text_id=  json_decode(json_encode($tid), true);
        
        $tg = DB::table('textgroups')->whereIn("id", $text_id)->get()->toArray();

        $textgroups =  json_decode(json_encode($tg), true);
        $textresult = ''; 
        $scoreresult=0;

        foreach ($textgroups as $textgroup) {
            $logic = json_decode($textgroup["logic"]);
            $content = json_decode($textgroup["content"]);
            $score = json_decode($textgroup["score"]);
            $flag =1;

            for($i=0;$i<count($logic);$i++)
            {
                for ($j=0; $j<count($logic[$i]); $j++)
                {
                    if ($j==0)
                    {
                        if ($logic[$i][$j][0] == 0)
                            $flag =1;
                        else
                            $flag=0;
                    }

                    $qt_id = $logic[$i][$j][1];
                    $ans= isset($logic[$i][$j][3]) ? $logic[$i][$j][3]: '';
                    $operator= $logic[$i][$j][2];
                
                    $real_ans =DB::table("user_answer")
                        ->select("answer")
                        ->where("user_id",$user_id)
                        ->where("question_id",$qt_id)
                        ->get()
                        ->pluck('answer')
                        ->toArray(); 

                    $process_flag =0;
                    if (is_array($real_ans))
                    {
                        for ($t=0;$t<count($real_ans);$t++)
                        {
                            if ($operator ==0 )
                            {
                                if ($real_ans[$t]==$ans) {$process_flag = 1;break;}
                                else    $process_flag = 0;
                            }
                                
                            else if($operator == 1)
                            {
                                if ($real_ans[$t] !=$ans) {$process_flag = 1;break;}
                                else    $process_flag = 0;
                            }                            
                            else if($operator == 2)
                            {
                                if (strpos($real_ans[$t],$ans) != false) {$process_flag = 1;break;}
                                else    $process_flag = 0;
                            }
                            
                            else if($operator == 3)
                            {
                                if (strpos($real_ans[$t],$ans) == false) {$process_flag = 1;break;}
                                else    $process_flag = 0;
                            }
                            
                            else if($operator == 4)
                            {
                                if (floatval($real_ans[$t]) > floatval($ans)) {$process_flag = 1;break;}
                                else    $process_flag = 0;
                            }
                                
                            else if($operator == 5)
                            {
                                if (floatval($real_ans[$t]) < floatval($ans)) {$process_flag = 1;break;}
                                else    $process_flag = 0;
                            }
                            else if($operator == 6)
                            {
                                if (floatval($real_ans[$t]) >= floatval($ans)) {$process_flag = 1;break;}
                                else    $process_flag = 0;
                            }
                            else if($operator == 7)
                            {
                                if (floatval($real_ans[$t]) <= floatval($ans)) {$process_flag = 1;break;}
                                else    $process_flag = 0;
                            }                                
                        }
                    }
                    else
                    {
                        if ($operator ==0 )
                        {
                            if ($real_ans==$ans) {$process_flag = 1;}
                            else  $process_flag = 0;
                        }
                                
                        else if($operator == 1)
                        {
                            if ($real_ans !=$ans) {$process_flag = 1;}
                            else    $process_flag = 0;
                        }                            
                        else if($operator == 2)
                        {
                            if (strpos($real_ans,$ans) != false) {$process_flag = 1;}
                            else    $process_flag = 0;
                        }
                            
                        else if($operator == 3)
                        {
                            if (strpos($real_ans,$ans) == false) {$process_flag = 1;}
                            else    $process_flag = 0;
                        }
                            
                        else if($operator == 4)
                        {
                            if (floatval($real_ans) > floatval($ans)) {$process_flag = 1;}
                            else    $process_flag = 0;
                        }
                            
                        else if($operator == 5)
                        {
                            if (floatval($real_ans) < floatval($ans)) {$process_flag = 1;}
                            else    $process_flag = 0;
                        }
                        else if($operator == 6)
                        {
                            if (floatval($real_ans) >= floatval($ans)) {$process_flag = 1;}
                            else    $process_flag = 0;
                        }
                        else if($operator == 7)
                        {
                            if (floatval($real_ans) <= floatval($ans)) {$process_flag = 1;}
                            else    $process_flag = 0;
                        }
                    }

                    if ($logic[$i][$j][0]==0)                
                        $flag *= $process_flag;
                    else
                        $flag += $process_flag;
                }

                if($flag != 0)
                {
                    $textresult .= '<h5>'.$content[$i].'</h5>';
                    $scoreresult = $score[$i];
                    break;
                }
            }

            if($flag == 0)
            {
                $textresult .= '<h5>'.$content[count($content)-1].'</h5>';
                $scoreresult = $score[count($score)-1];
            }
        }
            
        $output = array(
            'textresult'=>$textresult
        );
        
        DB::table('user_textgroup')
            ->insert([
                'textgroup_id' => data_get($text_id, [0, 'text_id']),
                'answer' => $textresult,
                'score'=>$scoreresult,
                'user_id' =>$user_id 
            ]);

         echo json_encode($output); 
    }

    public function get_answer(Request  $request)
    {
        $user_id = auth()->user()->id;

        $data= DB::table('user_answer')
        ->select('answer')
        ->where('question_id','=',$request->question_id)
        ->where('user_id','=',$user_id)
        ->get();  
         echo json_encode($data);   
    }

    public function get_chart_options(Request  $request)
    {
        $reqData = $request->all();
        $data = [
            'ctxData' => DB::table('charts')->where('id', (int)$reqData["id"])->get("ctxData"),
        ];
        return response()->json($data, 200);
    }

    public function get_report(Request  $request)
    {  
        $data = $request->test;
        $test_id = $request->test_id;
        $user_id = auth()->user()->id;
        $reported = $request->reported;

       
        //$score_temp = '0';
        $answers = DB::table('user_answer')->where('test_id', $test_id)->where('user_id', $user_id)->get();

        foreach($answers as $ans){
            $questionId = explode('-', $ans->question_id)[1] ?? $ans->question_id;
            $questionId = preg_replace('/[^0-9]/', '', $questionId);
            //$QuestionType = DB::table('questions')->select('questiontype')->where('id', $ans->question_id)->first()->questiontype;
            
            $temp = DB::table('questions')->select('questiontype')->where('id', $questionId)->first();

            if ($temp) {
                $QuestionType = $temp->questiontype;
            
                if($QuestionType != 7) {
                    DB::table('user_answer')->where('user_id', \Auth::id())->where('test_id', $test_id)->where('question_id', $ans->question_id)->delete();
                }
            } else {
                // $data = [
                //     "error"=> "Contact Admin: Question type not found for test_id ".$test_id.' question_id '.$ans->question_id . ' - ' . $questionId
                // ];
                // echo json_encode($data);
                // exit();
            }
        }
        if ($data == null) {
            $data = [
                    "error"=> "Contact Admin: arr = collecting_answers() == null "
                ];
                echo json_encode($data);
                exit();
        }
        foreach($data as $obj){
            $dataset = (array)$obj;
            $val = data_get($dataset, 'value');

            if(!data_get($dataset, 'key')) {
                continue;
            }

            $question_val = DB::table('questions')
                ->select('content', 'questiontype')
                ->where('id','=', data_get($dataset, 'key'))
                ->first();

            if($question_val != null && intval($val)>0) {

                if ($question_val->questiontype == 5) {
                    $content = json_decode($question_val->content);
                    $vals = $content[intval($val) - 1];
                    $val = $vals->score;
                
                } else if ($question_val->questiontype == 8) {
                    $content = json_decode($question_val->content);
                    $vals = $content[intval($val) - 1];
                    $val = $vals->score;
                } else if($question_val->questiontype == 4) {
                    $type = 'matrix-type="radio"';
                    $content_str = $question_val->content;
                    $pos = strpos($content_str, $type);
                    if($pos === false){
                        $val = 0;
                    }
                }
            }
            
            DB::table('user_answer')
                ->insert([
                    'question_id' => data_get($dataset, 'key'),
                    'q_id' => data_get($dataset, 'qid'),
                    'answer' => $val,
                    'user_id' =>$user_id ,
                    'test_id' =>$test_id ,
                    'score' => $val
                ]);
        }

        $user_id = auth()->user()->id;
        $report_id = DB::table('testreport_test')
        ->select('testreport_id')
        ->where('test_id','=',$test_id)
        ->first();
        if($report_id != null){
            if($reported){
                DB::table('user_test')->insert([
                    'user_id' => $user_id,
                    'test_id' => $test_id,
                    'reported' => $reported,
                ]);
            }
            $id = $report_id->testreport_id;
            $report_data = DB::table('testreports')->where('id',$id)->get();
            $user_question = DB::table('user_answer')->select('question_id', 'q_id', 'answer','score')->where('user_id',$user_id)->where('test_id',$test_id)->get();
            $user_textgroup = DB::table('user_textgroup')->select('textgroup_id','answer','score')->where('user_id',$user_id)->get();
            $textgroup_data = DB::table('textgroups')->get();
            // $chart_data = [];
            // $chart_ids = DB::table('chart_test')->where('test_id', $test_id)->get();
            // foreach($chart_ids as $id){
            //     array_push($chart_data, DB::table('charts')->where('id', $id->chart_id)->first());
            // }
            $chart_data = DB::table('charts')->get();
            //$questions_data = DB::table('questions')->get();
    
            //added by Polaris
            $text_id= DB::table('textgroup_test')->select('text_id')->where("test_id", $test_id)->pluck('text_id')->toArray();
            
            $tg = DB::table('textgroups')->whereIn("id", $text_id)->get()->toArray();
            $textgroups =  json_decode(json_encode($tg), true);
            $textresult=[]; $scoreresult=0;

            foreach ($textgroups as $textgroup) {
                $logic = json_decode($textgroup["logic"]);
                $content = json_decode($textgroup["content"]);
                $score = json_decode($textgroup["score"]);
                $flag = 1;

                for ($i = 0; $i < count($logic); $i++) {
                    for ($j = 0; $j < count($logic[$i]); $j++) {
                        if ($j == 0) {
                            if ($logic[$i][$j][0] == 0) {
                                $flag = 1;
                            } else {
                                $flag = 0;
                            }
                        }
                        $qt_id = $logic[$i][$j][1];
                        $ans = isset($logic[$i][$j][3]) ? $logic[$i][$j][3] : '';
                        $operator = $logic[$i][$j][2];

                        $real_ans = DB::table("user_answer")
                            ->select("answer")
                            ->where("user_id", $user_id)
                            ->where("question_id", $qt_id)
                            ->get()
                            ->pluck('answer')
                            ->toArray();

                        $process_flag = 0;
                        if (is_array($real_ans)) {
                            for ($t = 0; $t < count($real_ans); $t++) {
                                if ($operator == 0) {
                                    if ($real_ans[$t] == $ans) {
                                        $process_flag = 1;
                                        break;
                                    }
                                } else if ($operator == 1) {
                                    if ($real_ans[$t] != $ans) {
                                        $process_flag = 1;
                                        break;
                                    }
                                } else if ($operator == 2) {
                                    if (strpos($real_ans[$t], $ans)) {
                                        $process_flag = 1;
                                        break;
                                    }
                                } else if ($operator == 3) {
                                    if (!strpos($real_ans[$t], $ans)) {
                                        $process_flag = 1;
                                        break;
                                    }
                                } else if ($operator == 4) {
                                    if (floatval($real_ans[$t]) > floatval($ans)) {
                                        $process_flag = 1;
                                        break;
                                    }
                                } else if ($operator == 5) {
                                    if (floatval($real_ans[$t]) < floatval($ans)) {
                                        $process_flag = 1;
                                        break;
                                    }
                                } else if ($operator == 6) {
                                    if (floatval($real_ans[$t]) >= floatval($ans)) {
                                        $process_flag = 1;
                                        break;
                                    }
                                } else if ($operator == 7) {
                                    if (floatval($real_ans[$t]) <= floatval($ans)) {
                                        $process_flag = 1;
                                        break;
                                    }
                                }
                            }
                        } else {
                            if ($operator == 0) {
                                if ($real_ans == $ans) {
                                    $process_flag = 1;
                                }
                            } else if ($operator == 1) {
                                if ($real_ans != $ans) {
                                    $process_flag = 1;
                                }
                            } else if ($operator == 2) {
                                if (strpos($real_ans, $ans)) {
                                    $process_flag = 1;
                                }
                            } else if ($operator == 3) {
                                if (!strpos($real_ans, $ans)) {
                                    $process_flag = 1;
                                }
                            } else if ($operator == 4) {
                                if (floatval($real_ans) > floatval($ans)) {
                                    $process_flag = 1;
                                }
                            } else if ($operator == 5) {
                                if (floatval($real_ans) < floatval($ans)) {
                                    $process_flag = 1;
                                }
                            } else if ($operator == 6) {
                                if (floatval($real_ans) >= floatval($ans)) {
                                    $process_flag = 1;
                                }
                            } else if ($operator == 7) {
                                if (floatval($real_ans) <= floatval($ans)) {
                                    $process_flag = 1;
                                }
                            }
                        }

                        if ($logic[$i][$j][0] == 0)
                            $flag *= $process_flag;
                        else
                            $flag += $process_flag;
                    }

                    if ($flag != 0) {
                        $textresult[$textgroup['id']] = [
                            'id' => $textgroup['id'],
                            'content' => '<h5>' . $content[$i] . '</h5>',
                            'score' => $score[$i]
                        ];

                        $scoreresult = $score[$i];
                        break;
                    }
                }

                if ($flag == 0) {
                    $textresult[$textgroup['id']] = [
                        'id' => $textgroup['id'],
                        'content' => '<h5>' . $content[count($content) - 1] . '</h5>',
                        'score' => $score[count($score) - 1]
                    ];

                    $scoreresult = $score[count($score) - 1];
                }
            }

            $data = [
                "report_data" => $report_data,
                "user_question" => $user_question,
                "user_textgroup" => $user_textgroup,
                "chart_data" => $chart_data,
                "textgroup_data" => $textresult,
                //"questions_data" => $questions_data
            ];
        } else {
            $data = [
                "error"=> "There are no report on this test"
            ];
        }
        //return response()->json($data, 200);
        echo json_encode($data);
        exit();
    }
     
    public function update_report(Request $request)
    {

        $user_id = auth()->user()->id;
        $course_id = $request->course_id;
        $currentDate = Carbon::now()->timezone('Asia/Kolkata');;
        $dateCurrentDT = $currentDate->year .'-' .$currentDate->month .'-'.$currentDate->day .' '.$currentDate->hour .':'.$currentDate->minute;
        $userOrder = Order::where('user_id',$user_id)->get();

        $courseItēm = "";
        foreach($userOrder as $key => $order){
            $usercourse = OrderItem::where('order_id',$order->id)->where('item_id',$course_id)->get();
            if($usercourse != "[]"){
                $courseItēm .= $usercourse[0]->order_id;
            }
        }
        // $orderlimit = Order::where('id',$courseItēm)->get();

        // $expireDate = $orderlimit[0]->plan_date;

        // $strLTime =  strtotime($expireDate);
        // $current = strtotime($dateCurrentDT);
        // if($current <= $strLTime){
        
        $report_cont = $request->report_content;
        $tg_ids = json_decode($request->tg_ids);
        $tg_answers = json_decode($request->tg_answers);
        $tg_scores = json_decode($request->tg_scores);

        


        for($i = 0; $i < count($tg_ids); $i++){
            $ii=DB::table('user_textgroup')
            ->where('textgroup_id',$tg_ids[$i])
            ->where('user_id',$user_id)->count();
            // print_r($ii);
            // return $ii;
            if ($ii ==0)
            {
                DB::table('user_textgroup')
                    ->insert([
                        'textgroup_id' => $tg_ids[$i],
                        'answer' => $tg_answers[$i],
                        'user_id' => $user_id ,
                        'score' => $tg_scores[$i]
                    ]);
            }
            else{
                DB::table('user_textgroup')
                    ->where('textgroup_id',$tg_answers[$i])
                    ->where('user_id',$user_id)
                    ->update([
                        'answer' => $tg_answers[$i],
                        'score' => $tg_scores[$i]
                    ]);
            }
        }
        $report_id = DB::table('testreport_test')
            ->select('testreport_id')
            ->where('test_id','=', $request->test_id)
            ->first();
            
        if($report_id != null){
            $id = $report_id->testreport_id;
            DB::table('testreports')->where('id', $id)->update([
                'content' => $report_cont,
            ]);
        }
        $output = array(
            'success'  => 'i dati sono stati salvati correttamente' // data is saved successfully
        );
   
        echo json_encode($output);
    }

    public function show($course_id, $lesson_slug)
    {
        $test_id = $course_id; //add ckd
        if(isset($_GET['test_id'])){
            $test_id = (int)$_GET['test_id'];
        }
        // return $lesson_slug;

        $test_result = "";
        $completed_lessons = "";
        $lesson = Lesson::where('slug', $lesson_slug)->where('course_id', $course_id)->where('published', '=', 1)->first();

        if ($lesson == "") {
            $lesson = Test::where('slug', $lesson_slug)->where('course_id', $course_id)->where('published', '=', 1)->firstOrFail();
            $lesson->full_text = $lesson->description;
            $test_result = NULL;
            if ($lesson) {
                $test_result = TestsResult::where('test_id', $lesson->id)
                    ->where('user_id', \Auth::id())
                    ->first();
            }
        }
        
        if ((int)config('lesson_timer') == 0) {
            if(!$lesson->live_lesson){
                if ($lesson->chapterStudents()->where('user_id', \Auth::id())->count() == 0) {
                    $lesson->chapterStudents()->create([
                        'model_type' => get_class($lesson),
                        'model_id' => $lesson->id,
                        'user_id' => auth()->user()->id,
                        'course_id' => $lesson->course->id
                    ]);
                }
            }
        }

        $course_lessons = $lesson->course->lessons->pluck('id')->toArray();
        $course_tests = ($lesson->course->tests ) ? $lesson->course->tests->pluck('id')->toArray() : [];
        $course_lessons = array_merge($course_lessons,$course_tests);

        $previous_lesson = $lesson->course->courseTimeline()
            ->where('sequence', '<', $lesson->courseTimeline->sequence)
            ->whereIn('model_id',$course_lessons)
            ->orderBy('sequence', 'desc')
            ->first();

        $next_lesson = $lesson->course->courseTimeline()
            ->whereIn('model_id',$course_lessons)
            ->where('sequence', '>', $lesson->courseTimeline->sequence)
            ->orderBy('sequence', 'asc')
            ->first();

        $lessons = $lesson->course->courseTimeline()
            ->whereIn('model_id',$course_lessons)
            ->orderby('sequence', 'asc')
            ->get();



        $purchased_course = $lesson->course->students()->where('user_id', \Auth::id())->count() > 0;
        $test_exists = FALSE;

        if (get_class($lesson) == 'App\Models\Test') {
            $test_exists = TRUE;
        }

        $completed_lessons = \Auth::user()->chapters()
            ->where('course_id', $lesson->course->id)
            ->get()
            ->pluck('model_id')
            ->toArray();

        $percentage = [];
        // $testIds = \DB::table('question')
        // $test_questions = \DB::select("SELECT GROUP_CONCAT(q.id) as questions_id, t.id as testid,count(t.id) as question_count FROM courses c LEFT JOIN tests t ON c.id = t.course_id LEFT JOIN questions q ON t.id = q.test_id WHERE c.id = '$course_id' GROUP BY t.id ");
        // $test_questions = \DB::select("SELECT GROUP_CONCAT(q.id) as questions_id, t.id as testid,count(t.id) as question_count FROM courses c LEFT JOIN tests t ON c.id = t.course_id LEFT JOIN questions q ON JSON_CONTAINS(t.id, CAST(q.test_id AS CHAR)) WHERE c.id = '$course_id' GROUP BY t.id ");
        
        $test_id_f = $lesson->id;
        $user_answers = DB::table('user_answer')->where('user_id', \Auth::id())->where('test_id', $test_id)->get();
        
        // $question_count = DB::table('question_test')->where('test_id', $test_id)->count();

        $first_a;
        $answers_count = 0;
        foreach ($user_answers as $key => $value) {
            if($key == 0 || $value->question_id != $first_a){
                $first_a = $value->question_id;
                $answers_count++;
            }
        }

        if($lesson->questions == '[]'){
            $percentage[$test_id] = 0;
        }else{

            if($lesson->questions != null){
                $all_cnt = $lesson->questions->count();
                if($all_cnt == 0) $all_cnt = 1;
            }else {
                $all_cnt = 1;
            }
            if($answers_count === 0 || $all_cnt === 0){
                $percentage[$test_id] = 0;
            }else{
                $percentage[$test_id] = ($answers_count/$all_cnt)*100;
            }
            
        }
		$testreport =DB::table('testreports')->where('id',$lesson->course->id)->get();

        $reported = DB::table('user_test')->where('test_id', $test_id)->where('user_id', \Auth::id())->first();
                
        // $lesson->questions = $lesson->questions->sortBy('page_number');
        // dd($answers);
		//echo "<pre>"; print_r($test_exists); die();
		// return $this->path;  
      return view($this->path . '.courses.lesson', compact('lesson', 'previous_lesson', 'next_lesson', 'test_result',
            'purchased_course', 'test_exists', 'lessons', 'completed_lessons','percentage','test_id_f','test_id', 'reported'));
    }

    public function test($lesson_slug, Request $request)
    {
        $test = Test::where('slug', $lesson_slug)->firstOrFail();
        $answers = [];
        $test_score = 0;
        if(!$request->get('questions')){

            return back()->with(['flash_warning'=>'No options selected']);
        }
        foreach ($request->get('questions') as $question_id => $answer_id) {
            $question = Question::find($question_id);
            $correct = QuestionsOption::where('question_id', $question_id)
                    ->where('id', $answer_id)
                    ->where('correct', 1)->count() > 0;
            $answers[] = [
                'question_id' => $question_id,
                'option_id' => $answer_id,
                'correct' => $correct
            ];
            if ($correct) {
                if($question->score) {
                    $test_score += $question->score;
                }
            }
            /*
             * Save the answer
             * Check if it is correct and then add points
             * Save all test result and show the points
             */
        }
        $test_result = TestsResult::create([
            'test_id' => $test->id,
            'user_id' => \Auth::id(),
            'test_result' => $test_score,
        ]);
        $test_result->answers()->createMany($answers);


        if ($test->chapterStudents()->where('user_id', \Auth::id())->get()->count() == 0) {
            $test->chapterStudents()->create([
                'model_type' => $test->model_type,
                'model_id' => $test->id,
                'user_id' => auth()->user()->id,
                'course_id' => $test->course->id
            ]);
        }


        return back()->with(['message'=>'Test score: ' . $test_score,'result'=>$test_result]);
    }

    public function retest(Request $request)
    {
        $test = TestsResult::where('id', '=', $request->result_id)
            ->where('user_id', '=', auth()->user()->id)
            ->first();
        $test->delete();
        return back();
    }

    public function videoProgress(Request $request)
    {
        $user = auth()->user();
        $video = Media::findOrFail($request->video);
        $video_progress = VideoProgress::where('user_id', '=', $user->id)
            ->where('media_id', '=', $video->id)->first() ?: new VideoProgress();
        $video_progress->media_id = $video->id;
        $video_progress->user_id = $user->id;
        $video_progress->duration = $video_progress->duration ?: round($request->duration, 2);
        $video_progress->progress = round($request->progress, 2);
        if ($video_progress->duration - $video_progress->progress < 5) {
            $video_progress->progress = $video_progress->duration;
            $video_progress->complete = 1;
        }
        $video_progress->save();
        return $video_progress->progress;
    }

    public function courseProgress(Request $request)
    {
        if (\Auth::check()) {
            $lesson = Lesson::find($request->model_id);
            if ($lesson != null) {
                if ($lesson->chapterStudents()->where('user_id', \Auth::id())->get()->count() == 0) {
                    $lesson->chapterStudents()->create([
                        'model_type' => $request->model_type,
                        'model_id' => $request->model_id,
                        'user_id' => auth()->user()->id,
                        'course_id' => $lesson->course->id
                    ]);
                    return true;
                }
            }
        }
        return false;
    }

    public function bookSlot(Request $request)
    {
        $lesson_slot = LiveLessonSlot::find($request->live_lesson_slot_id);
        $lesson = $lesson_slot->lesson;

        if ((int)config('lesson_timer') == 0) {
            if ($lesson->chapterStudents()->where('user_id', \Auth::id())->count() == 0) {
                $lesson->chapterStudents()->create([
                    'model_type' => get_class($lesson),
                    'model_id' => $lesson->id,
                    'user_id' => auth()->user()->id,
                    'course_id' => $lesson->course->id
                ]);
            }
        }

        if(LessonSlotBooking::where('lesson_id', $request->lesson_id)->where('user_id', auth()->user()->id)->count() == 0){
            LessonSlotBooking::create(
                ['lesson_id' => $request->lesson_id, 'live_lesson_slot_id' => $request->live_lesson_slot_id, 'user_id' => auth()->user()->id]
            );
            \Mail::to(auth()->user()->email)->send(new StudentMeetingSlotMail($lesson_slot));
        }
        return back()->with(['success'=> __('alerts.frontend.course.slot_booking')]);
    }

    public function get_answers_fill(Request $request){
        $test_id = $request->test_id;
        $user_id = auth()->user()->id;
        $qu = DB::table('user_answer')->select('question_id')->where('user_id', $user_id)->where('test_id', $test_id)->groupBy('question_id')->get();

        $datas = [];

        foreach ($qu as $key => $value) {
            
            $data = [];
            $data['question_id'] = data_get($value, 'question_id');

            $question = DB::table('questions')->select('questiontype')->where('id', $data['question_id'])->first();
            $data['type'] = data_get($question, 'questiontype');

            $answers = DB::table('user_answer')
                ->select('id', 'q_id', 'answer', 'score')
                ->where('user_id', $user_id)
                ->where('test_id', $test_id)
                ->where('question_id', $data['question_id'])
                ->get();
                
            $data['ans'] = $answers;
            array_push($datas, $data);
        }

        $data = [
            'answers' => $datas,
        ];

        echo json_encode($data) ;
    }

    public function user_upload_files(Request $request)
    {
        $destinationPath = 'uploads/storage/';
        $file_names = []; 
        $exist_files = [];
        $image_types = ['jpg', 'jpeg', 'ico', 'png', 'bmp', 'gif', 'webp', 'svg', 'tif', 'pjp', 'xbm', 'jxl', 'tif', 'jfif', 'pjpeg', 'avif'];
        $totalfiles = $request->TotalFiles;
        $question = DB::table('questions')->where('id', $request->q_id)->first();
        $number_of_files = json_decode($question->content)[0]->number;
        if($totalfiles <= $number_of_files){
            $answer = DB::table('user_answer')->select('answer')->where('test_id', $request->test_id)->where('question_id', $request->q_id)->first();
            if($answer){
                $exist_files = $answer->answer;
                $exist_files = json_decode($exist_files);
            }

            if(is_array($exist_files) && count($exist_files) > 0){
                $file_names = $exist_files;
            }

            if($totalfiles > 0){
                for($x = 0; $x < $totalfiles; $x++){
                    if($request->hasfile('files'.$x))
                    {   
                        $file = $request->file('files'.$x);
                        $name = $request->test_id.'_'.\Auth::id().'_'.$file->getClientOriginalName();
                        $extension = $file->getClientOriginalExtension();
                        $check = $file->move($destinationPath, $name);
                        if(in_array($extension, $image_types)){
                            array_push($file_names, [
                                'name' => $name,
                                'type' => 'image',
                            ]);
                        }else {
                            array_push($file_names,[
                                'name' => $name,
                                'type' => 'other',
                            ]);
                        }
                    }
                }
            }
            // DB::table('user_answer')->where('test_id', $request->test_id)->where('question_id', $request->q_id)->delete();
            if($answer){
                DB::table('user_answer')->where('test_id', $request->test_id)->where('question_id', $request->q_id)->update([
                    'user_id' => \Auth::id(),
                    'test_id' => $request->test_id,
                    'question_id' => $request->q_id,
                    'q_id' => $request->q_id,
                    'answer' => json_encode($file_names),
                    'score' => 0,
                ]);
            }else {
                DB::table('user_answer')->where('test_id', $request->test_id)->where('question_id', $request->q_id)->insert([
                    'user_id' => \Auth::id(),
                    'test_id' => $request->test_id,
                    'question_id' => $request->q_id,
                    'q_id' => $request->q_id,
                    'answer' => json_encode($file_names),
                    'score' => 0,
                ]);
            }
            $output = array(
                'result'  => 'File caricati correttamente',
                'file_names' =>$file_names,
                'q_id' => $request->q_id
            );
        }else {
            $output = array(
                'result'  => 'Il numero di file deve essere inferiore a '.$number_of_files,
                'file_names' =>$file_names,
                'q_id' => $request->q_id
            );
        }
        return response()->json($output);
    }

    public function delete_file(Request $request){
        $test_id = $request->test_id;
        $question_id = $request->question_id;
        $name = $request->name;
        $user_id = \Auth::id();
        $files = DB::table('user_answer')->select('answer')->where('user_id', $user_id)->where('test_id', $test_id)->where('question_id', $question_id)->first()->answer;
        $files = json_decode($files);
        foreach ($files as $key => $file) {
            if($file->name == $name){
                array_splice($files, $key, 1);  
            }
        }
        $files = json_encode($files);
        DB::table('user_answer')->where('user_id', $user_id)->where('test_id', $test_id)->where('question_id', $question_id)->update([
            'user_id' => $user_id,
            'test_id' => $test_id,
            'question_id' => $question_id,
            'answer' => $files
        ]);

        $output = array(
            'success' => 'File eliminati con successo'
        );
        return response()->json($output);
    }

    public function drop(){
        $all_table_names = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();

        foreach ($all_table_names as $name) {
            //if you don't want to truncate migrations in Database
            if ($name == 'migrations') {
                continue;
            }
            DB::table($name)->delete();
        }
        return 'success';
    }

    public function getUserHelp(Request $request){

        $idArr = json_decode($request->qustId);

        $questions = Question::whereIn('id',$idArr)->get();
        $returnData = [];
        $qusno = 1;
        $help =[];  
        foreach($questions as $key => $inputText){
            
            //$question = Question::where('id',$value)->get();
            //$inputText = $question[0];
            // Define the regular expression pattern to match the dynamic value within square brackets
            if($inputText->hint_info != "" && $inputText->access_hint_info == 1){

                $patternA = "/\[text id=(\d+)\]/";
                $patternB = "/\[chart id=(\d+)\]/";
                // Use preg_match to find matches and extract the dynamic value
                if (preg_match($patternA, $inputText->hint_info, $matches)) {
                    $textGroup = DB::table('textgroups')->where('short_code',$matches)->get();
                    $help['content'] = "<div style='border: 1px solid $inputText->color1;padding: 5px;'><p><b><span style='font-size: 17px !important;font-weight: bold;' class='setStyleDiv' >Q".$qusno++." </span></b></br><div style='word-wrap: break-word; >".$textGroup[0]->title."</div></p></div>";
                    $help['step'] = 1;
                } else {
                    if(preg_match($patternB, $inputText->hint_info, $matches)){
                        $charts = DB::table('charts')
                        ->where('short_code',$matches)->get();
                        $help['chartid'] = $charts[0]->id;
                        $help['content'] = $charts[0]->content;
                        $help['shortcode'] = $matches;
                        $help['type'] = $charts[0]->type;
                        $help['qusno'] = $qusno++;
                        $help['step'] = 2;
                    }else{
                        $help['content'] = "<div style='border: 1px solid $inputText->color1;padding: 5px;word-wrap: break-word;' ><span style='font-size: 17px !important;font-weight: bold;'>Q".$qusno++." </span></br><p  class='setStyleDiv'>". strip_tags($inputText->hint_info) ."</p></div>";
                        $help['step'] = 3;
                    }
                }
            }else{
                $help['content'] = "";
                $help['step'] = 4;
                $qusno++;
            }
            
            // $qusno++;
            $returnData[] = $help;

        }
        // return $help['content'];
        
        echo json_encode($returnData);
    }

    

    public function getUserForModelHelp(Request $request){

        $idArr = json_decode($request->qustId);

        $questions = Question::whereIn('id',$idArr)->get();
        $returnData = [];
        $qusno = 1;
        $help =[];  
        foreach($questions as $key => $inputText){
            //$question = Question::where('id',$value)->get();
            //$inputText = $question[0];
            // Define the regular expression pattern to match the dynamic value within square brackets
            if($inputText->help_info != ""){

                $patternA = "/\[text id=(\d+)\]/";
                $patternB = "/\[chart id=(\d+)\]/";
                // Use preg_match to find matches and extract the dynamic value
                if (preg_match($patternA, $inputText->help_info, $matches)) {
                    $textGroup = DB::table('textgroups')->where('short_code',$matches)->get();
                    $help['content'] = $textGroup[0]->title;
                    $help['step'] = 1;
                } else {
                    if(preg_match($patternB, $inputText->help_info, $matches)){
                        $charts = DB::table('charts')
                        ->where('short_code',$matches)->get();
                        $help['chartid'] = $charts[0]->id;
                        $help['content'] = $charts[0]->content;
                        $help['shortcode'] = $matches;
                        $help['type'] = $charts[0]->type;
                        $help['qusno'] = $qusno++;
                        $help['step'] = 2;
                    }else{
                        $help['content'] =  $inputText->help_info;
                        $help['step'] = 3;
                    }
                }
            }else{
                $help['content'] = "";
                $help['step'] = 4;
                $qusno++;
            }
            
            // $qusno++;
            $returnData[] = $help;

        }
        // return $help['content'];
        
        echo json_encode($returnData);
    }

     public function getQustionGraph(Request $request){

        $idArr = json_decode($request->qustId);

        $questions = Question::whereIn('id',$idArr)->get();
        $returnData = [];
        $qusno = 1;
        $help =[];  
        foreach($questions as $key => $inputText){
            //$question = Question::where('id',$value)->get();
            //$inputText = $question[0];
            // Define the regular expression pattern to match the dynamic value within square brackets
            if($inputText->question != ""){

                $patternA = "/\[text id=(\d+)\]/";
                $patternB = "/\[chart id=(\d+)\]/";
                // Use preg_match to find matches and extract the dynamic value
                if (preg_match($patternA, $inputText->question, $matches)) {
                    $textGroup = DB::table('textgroups')->where('short_code',$matches)->get();
                    $help['content'] = $textGroup[0]->title;
                    $help['step'] = 1;
                } else {
                    if(preg_match($patternB, $inputText->question, $matches)){
                        $charts = DB::table('charts')
                        ->where('short_code',$matches)->get();
                        $help['chartid'] = data_get($charts, [0, 'id']);
                        $help['content'] = data_get($charts, [0, 'content']);
                        $help['shortcode'] = $matches;
                        $help['type'] = data_get($charts, [0, 'type']);
                        $help['qusno'] = $qusno++;
                        $help['step'] = 2;
                    }else{
                        $help['content'] =  $inputText->question;
                        $help['step'] = 3;
                    }
                }
            }else{
                $help['content'] = "";
                $help['step'] = 4;
                $qusno++;
            }
            
            // $qusno++;
            $returnData[] = $help;

        }
        // return $help['content'];
        
        echo json_encode($returnData);
    }
    
}