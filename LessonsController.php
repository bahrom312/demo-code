<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Carbon
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

// Models
use App\Models\Lessons_calendar_model;
use App\Models\Lessons_presence_model;
use App\Models\Students_model;
use App\Models\Groups_model;
use App\Models\Teachers_model;
use App\Models\Students_oplata_model;

// Exceptions
use Exception;

// Laravel Debugbar
use Barryvdh\Debugbar\Facades\Debugbar;

// Excel
use Maatwebsite\Excel\Facades\Excel;

use \Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;




class LessonsController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }



    /**
     * Show Lessons Calendar table
     *
     * @return View
     */
    public function lesson_calendar(Request $request)
    {

        // find_date field from lessons_calendar_table_page
        $find_date_text = $request->input('find_date') ?? '';

        // Set find_date to today if it is not a valid date
        try {
            $find_date_carbon = CarbonImmutable::parse($find_date_text);
        }
        catch(Exception $e) {
            // $find_date = false;
            $find_date_carbon = CarbonImmutable::today();
        }


        $find_date = $find_date_carbon->timestamp;

        // Find the first and last day of the month
        $find_month_start = $find_date_carbon->startOfMonth();
        $find_month_end = $find_date_carbon->endOfMonth();

        // Find the first and last day of the week
        $start_date = $find_month_start->startOfWeek();
        $end_date = $find_month_end->endOfWeek();

        // Convert to SQL format
        $start_date_sql = $start_date->format('Y-m-d');
        $end_date_sql = $end_date->format('Y-m-d');

        // Links to previous and next month
        $prev_month_date_dmy = $find_date_carbon->startOfMonth()->subMonth(1)->format('d.m.Y');
        $next_month_date_dmy = $find_date_carbon->startOfMonth()->addMonth(1)->format('d.m.Y');

        // Find the number of days and weeks in the month
        $diff_days = $start_date->diffInDays($end_date) + 1;
        $weeks_count = $start_date->diffInWeeks($end_date) + 1;

        // Create an array of dates for the month
        $period = CarbonPeriod::create($start_date, $end_date);
        $period_array = [];

        // Iterate over the period
        foreach ($period as $date_carbon) {
            // $period_array['date_carbon'][] = $date_carbon;
            $period_array[] = [
                'date_carbon' => $date_carbon,
                'date_sql' => $date_carbon->format('Y-m-d'),
                'date_dmy' => $date_carbon->format('d.m.Y'),
                'week_day' => $date_carbon->isoWeekday(),
            ];
            // echo $date_carbon->format('Y-m-d')."\n";
        }


        // Get lessons for the month
        $lessons_array = Lessons_calendar_model::whereBetween('lesson_date', [$start_date_sql, $end_date_sql])
                                                ->with('group')
                                                ->get();
        
        // Return the view
        return view('lessons.lessons_calendar_table_page', compact(
            'find_date',
            'find_date_text',
            // 'find_month_start',
            // 'find_month_end',
            // 'start_date',
            // 'end_date',
            // 'start_date_sql',
            // 'end_date_sql',
            'prev_month_date_dmy',
            'next_month_date_dmy',
            'diff_days',
            'weeks_count',
            'period_array',
            'lessons_array'
        ));
    }


    /**
     * Edit Lesson_calendar_model
     *
     * @param Request $request
     * @param int $id
     *
     * @return View
     */
    public function lesson_calendar_edit(Request $request, int $id = 0): View
    {
        Debugbar::disable();

        $action = $request->input('action') ?? '';
        $lesson_date = $request->input('lesson_date') ?? '';

        // Create a new Lesson in Calendar
        if ($id == 0) {
            $lesson_calendar_row = new Lessons_calendar_model;
            $lesson_calendar_row->lesson_date = Carbon::parse($lesson_date)->format('Y-m-d');
            // $student_row = null;
        }
        // Otherwise, load the Gruppa with the given $id
        else {
            $lesson_calendar_row = Lessons_calendar_model::find($id);
        }

        $teachers_list = Teachers_model::all();
        $groups_list = Groups_model::all();


        return view('lessons.lessons_calendar_edit_page', compact(
            'lesson_calendar_row',
            'teachers_list',
            'groups_list',
            'action',
            'lesson_date'
        ));

    } //function lesson_calendar_edit



    
    /**
     * Save Lesson_calendar_model
     *
     * @param Request $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function lesson_calendar_save(Request $request, $id = 0)
    {
        Debugbar::disable();
        $group_id = $request->group_id ?? 0;

        // return a error response
        if ($group_id == 0 or $request->lesson_date == null) {
            // return response status 505 and json
            return response()->json(['success' => false], 500);
        }

        if ($id == 0) {
            $lesson_calendar = new Lessons_calendar_model();
        }
        else
        {
            // get the student data from the request
            $lesson_calendar = Lessons_calendar_model::find($id);
        }

        // dd(Auth::user());
        // dd($request->lesson_date);
        $lesson_calendar->lesson_date = Carbon::parse($request->lesson_date)->format('Y-m-d');
        $lesson_calendar->group_id = $request->group_id ?? null;

        $lesson_calendar->lesson_start_time = $request->lesson_start_time ?? null;
        $lesson_calendar->lesson_end_time = $request->lesson_end_time ?? null;

        $lesson_calendar->lesson_info = $request->lesson_info ?? null;

        $lesson_calendar->username = Auth::user()->username ?? null;

        $lesson_calendar->save();


        // return a response to indicate success or failure
        if ($lesson_calendar) {
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false]);
        }


    }  // function lesson_calendar_save



    /**
     * function  lessons_calendar_delete
     *
     * @param Request $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function lesson_calendar_delete(Request $request, int $id = 0): JsonResponse
    {

        $lesson_id = $request->lesson_id ?? 0;

        // delete the lesson
        $lesson_calendar = Lessons_calendar_model::find($lesson_id);
        $lesson_calendar->delete();

        // return a response to indicate success or failure
        if ($lesson_calendar) {
            return response()->json(['success' => true], 200);
        } else {
            return response()->json(['success' => false], 500);
        }

    } // function  lessons_calendar_delete




    /**
     * Lesson_presence Table show
     *
     * @param Request $request
     *
     * @return View
     */
    public function lesson_presence(Request $request)
    {

        $find_group_id = $request->input('find_group_id') ?? 1;

        // find_date field from lessons_calendar_table_page
        $find_date_text = $request->input('find_date') ?? CarbonImmutable::today()->startOfMonth()->format('d.m.Y');

        // Set find_date to today if it is not a valid date
        try {
            $find_date_carbon = CarbonImmutable::parse($find_date_text);
        }
        catch(Exception $e) {
            // $find_date = false;
            $find_date_carbon = CarbonImmutable::today();
        }


        $find_date = $find_date_carbon->timestamp;

        $find_date_month = $find_date_carbon->month;

        // Month manes array in russian
        $month_names = array(
            1 => 'Январь',
            2 => 'Февраль',
            3 => 'Март',
            4 => 'Апрель',
            5 => 'Май',
            6 => 'Июнь',
            7 => 'Июль',
            8 => 'Август',
            9 => 'Сентябрь',
            10 => 'Октябрь',
            11 => 'Ноябрь',
            12 => 'Декабрь'
        );

        $find_date_month_name = $month_names[$find_date_month];



        // Find the first and last day of the month
        $find_month_start = $find_date_carbon->startOfMonth();
        $find_month_end = $find_date_carbon->endOfMonth();

        // Convert to SQL format
        $find_month_start_sql = $find_month_start->format('Y-m-d');
        $find_month_end_sql = $find_month_end->format('Y-m-d');

        // Links to previous and next month
        $prev_month_date_dmy = $find_date_carbon->startOfMonth()->subMonth(1)->format('d.m.Y');
        $next_month_date_dmy = $find_date_carbon->startOfMonth()->addMonth(1)->format('d.m.Y');

        // array of last 12 months from NOW for select box in the form of [month_number => 'd.m.Y']
        $prev_months_array = array();
        for ($i = 0; $i < 12; $i++) {
            $prev_months_array[$find_date_carbon->subMonth($i)->month]['date'] = Carbon::now()->subMonth($i)->startOfMonth()->format('d.m.Y');
            $prev_months_array[$find_date_carbon->subMonth($i)->month]['month_name'] = $month_names[Carbon::now()->subMonth($i)->startOfMonth()->month];
            $prev_months_array[$find_date_carbon->subMonth($i)->month]['year'] = Carbon::now()->subMonth($i)->startOfMonth()->format('Y');
        }


        $group_row = Groups_model::find($find_group_id);
        $group_students = Groups_model::find($find_group_id)->students()->get();
        // dd($group_students);

        // Groups array for the select box
        $groups_array = Groups_model::all();

        $students_oplata_array = Students_oplata_model::query()
                                ->where('oplata_date', '>=', $find_month_start_sql)
                                ->where('oplata_date', '<=', $find_month_end_sql)
                                ->get();
        // dd($students_oplata_array);
        
        // Lessons array for the month
        $lessons_array = Lessons_calendar_model::where('group_id', $find_group_id)
            ->where('lesson_date', '>=', $find_month_start_sql)
            ->where('lesson_date', '<=', $find_month_end_sql)
            ->orderBy('lesson_date', 'asc')
            ->get();


        return view('lessons.lessons_presence_table_page', compact(
                         'find_date_text',
                         'find_group_id',
                         'group_row',
                         'group_students',
                         'lessons_array',
                         'groups_array',
                         'prev_month_date_dmy',
                         'next_month_date_dmy',
                         'find_date_month_name',
                         'prev_months_array',
                         'students_oplata_array'
        ));


    } // function lesson_presence


    /**
     * Lesson_presence Table show
     *
     * @return JsonResponse
     */
    function lesson_status_update(){

            Debugbar::disable();

            $lesson_id = request('lesson_id', 0);
            $student_id = request('student_id', 0);
            $student_status = request('student_status',0);


            $lesson_presence = Lessons_presence_model::where('lesson_id', $lesson_id)
                                                        ->where('student_id', $student_id)
                                                        ->first();

            if ($lesson_presence) {
                $lesson_presence->student_status = $student_status;
                $lesson_presence->save();
            }
            else {
                $lesson_presence = new Lessons_presence_model();
                $lesson_presence->lesson_id = $lesson_id;
                $lesson_presence->student_id = $student_id;
                $lesson_presence->student_status = $student_status;
                $lesson_presence->save();
            }

            return response()->json(['success' => true], 200);

    } // function set_student_lesson_status



    /**
     * Edit Page Lesson_calendar form
     *
     * @param Request $request
     * @param integer $id
     *
     * @return View
     */
    public function edit_lesson(Request $request, $id = 0)
    {
        Debugbar::disable();

        $action = $request->input('action', 'new');
        $group_id = $request->input('group_id', 0);

        // Create a new student
        if ($id == 0) {
            $lesson_calendar_row = new Lessons_calendar_model;
            $lesson_calendar_row->group_id = $group_id;
        }
        // Otherwise, load the Gruppa with the given $id
        else {
            $lesson_calendar_row = Lessons_calendar_model::find($id);
        }

        // $teachers_list = Teachers_model::all();
        $groups_list = Groups_model::all();
        $group_row = Groups_model::find($group_id);


        return view('lessons.lessons_presence_edit_page', compact(
                     'lesson_calendar_row',
                     'group_row',
                     'groups_list',
                     'group_id',
                     'action',
        ));

    } //function lesson_calendar_edit




    /**
     * Save Lesson_calendar_model
     *
     * @param Request $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function save_lesson(Request $request, $id = 0)
    {
        $group_id = $request->group_id ?? 0;
        $lesson_date = $request->lesson_date ?? null;

        // return a error response
        if ($group_id == 0 or $lesson_date == null) {
            // return response status 505 and json
            return response()->json(['success' => false], 500);
        }

        if ($id == 0) {
            $lesson_calendar = new Lessons_calendar_model();
        }
        else
        {
            // get the student data from the request
            $lesson_calendar = Lessons_calendar_model::find($id);
        }

        // dd(Auth::user());
        // dd($request->lesson_date);
        $lesson_calendar->lesson_date = Carbon::parse($request->lesson_date)->format('Y-m-d');
        $lesson_calendar->group_id = $request->group_id ?? null;

        $lesson_calendar->lesson_start_time = $request->lesson_start_time ?? null;
        $lesson_calendar->lesson_end_time = $request->lesson_end_time ?? null;

        $lesson_calendar->lesson_info = $request->lesson_info ?? null;

        $lesson_calendar->username = Auth::user()->username ?? null;

        $lesson_calendar->save();


        // return a response to indicate success or failure
        if ($lesson_calendar) {
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false]);
        }


    }  // function lesson_calendar_save




    /**
     * Delete Lesson_calendar_model
     * @param Request $request
     * @return JsonResponse
     */
    public function delete_lesson(Request $request)
    {

        $lesson_id = $request->lesson_id ?? 0;

        // delete the lesson
        $lesson_calendar = Lessons_calendar_model::find($lesson_id);
        $lesson_calendar->delete();

        // return a response to indicate success or failure after delete model
        if ($lesson_calendar) {
            return response()->json(['success' => true], 200);
        } else {
            return response()->json(['success' => false], 500);
        }

    } // function  lessons_calendar_delete







}
