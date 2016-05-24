<?php

namespace App\Http\Controllers\Duty;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Requests\DutyRequest;


use App\Http\Controllers\Controller;

use Input;
use Carbon\Carbon;
Use PDF;
use Redirect;
Use DB;
use Validator;
use Auth;



use App\Models\Duty;
use App\Models\Contact;
use App\Models\PerDiemTypes;
use App\Models\PerDiem;
use App\Models\Airport;
use App\Models\DutyType;
use App\Models\Leg;
use App\Models\DutyPayTypes;
use App\Models\DutyPay;
use App\Models\User;

//use OwenIt\Auditing\Log;

//use Venturecraft\Revisionable\Revision;

class DutyController extends Controller {


	public function test()
	{
		return 'Hello world';
	}

	public function duty_month($month, $year)

		{
			$links = DB::table('duties')
			    ->select(DB::raw('YEAR(duty_end) year, MONTH(duty_end) month, MONTHNAME(duty_end) month_name, COUNT(*) post_count'))
			    ->groupBy('year')
			    ->groupBy('month')
			    ->orderBy('year', 'desc')
			    ->orderBy('month', 'desc')
			    ->get();

			$duties=Duty::where(DB::Raw('MONTH(duty_end)'),$month)
						->where (DB::Raw('YEAR(duty_end)'),$year)
						->get();
			   


		//return $duties;	
		return view('duties.index')->with('duties',$duties)->with('links',$links);

		}

	public function flightduty($month, $year)

		{
				$user_id=Auth::user()->id;

				$links = DB::table('duties')
				    ->select(DB::raw('YEAR(duty_end) year, MONTH(duty_end) month, MONTHNAME(duty_end) month_name, COUNT(*) post_count'))
				    ->groupBy('year')
				    ->groupBy('month')
				    ->orderBy('year', 'desc')
				    ->orderBy('month', 'desc')
				    ->get();

				$duties=Duty::where(DB::Raw('MONTH(duty_end)'),$month)
							->where (DB::Raw('YEAR(duty_end)'),$year)
							->where (DB::Raw('contact_id'),$user_id)
							->where('type_id',1)
							->orderBy('duty_end')
							->get();

		//return $duties;	
		return view('duties.flightduty.index')->with('duties',$duties)->with('links',$links);

		}


	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */

	public function index()

		{
			
			$duties=Duty::all();
			//$logs=Revision::all();
			
			

			return view('pages.duties.index')->with('duties',$duties);



		}




	
     

	public function perdiem($month, $year,$contact_id = null)

		{


			
			
			if(empty($contact_id)) {
				$contact_id=Auth::user()->contact_id;

			}

			$period_start=Carbon::now()->year($year)->month($month)->startOfMonth();
			$period_end=Carbon::now()->year($year)->month($month)->endOfMonth();


			//eager loading with constraint

			$duties=Duty::with(['per_diem' => function ($query) use ($period_start,$period_end) { $query->whereBetween('to_date', [$period_start,$period_end]);}])
						
						->where('contact_id',$contact_id)
						->Where(function ($query) use ($period_start,$period_end) {
			                $query->whereBetween('duty_start',[$period_start,$period_end])
			                      ->orWhereBetween('duty_end',[$period_start,$period_end]);
			                  })
						->get();

		
			$perdiems=PerDiem::with('per_diem_overnight')
							->where(DB::Raw('MONTH(to_date)'),$month)
							->where (DB::Raw('YEAR(to_date)'),$year)
							->whereHas('duty',function ($query) use ($contact_id) {
								$query->where('contact_id', $contact_id);

								})
							->orderBy('duty_id')
							->get();
						
			
			$duty_pay=DutyPay::where(DB::Raw('MONTH(to_date)'),$month)
							->where (DB::Raw('YEAR(to_date)'),$year)
							->whereHas('duty',function ($query)  use ($contact_id) {
								$query->where('contact_id', $contact_id);

								})
						             
							->get();	
	

			$dutiespaytypes=DutyPayTypes::with(['duty_pay' => function ($query) use ($period_start,$period_end) {
							$query->whereBetween('to_date',[$period_start,$period_end]);
							}])->get();





			$contact=Contact::find($contact_id);

			//Diett innland uten overnatting
			//$diem_domestic_withoutovernight=PerDiem::LocalDiem()->get();	

			//Diett innland med overnatting									
			//$diem_domestic_withovernight=$perdiems->where(DB::raw('per_diem_overnight.country_code'),'NO');

			$sum_diem=$perdiems->sum('calc_per_diem') + $perdiems->sum('value_rule_six_hour');
						
		
		
			//$duties4=Duty::all()->first();

			//$duties4=$duties4->per_diem()->where('to_date','<',$duties4->duty_end->endOfMonth())->get();
			
		

			$links = DB::table('duties')
			    ->select(DB::raw('YEAR(duty_end) year, MONTH(duty_end) month, MONTHNAME(duty_end) month_name, COUNT(*) post_count'))
			    ->groupBy('year')
			    ->groupBy('month')
			    ->orderBy('year', 'desc')
			    ->orderBy('month', 'desc')
			    ->get();
		//$duties=Duty::where(function($item) { 

		//	return $item->duty_end->format('m'); 

		//	},9)->get();
						

						
		//$data=array('duties' => $duties);	
		
		//return view('duties.dutyreport');
		//return PDF::loadView('duties.old.dutyreport',compact('duties'))->stream('github.pdf');
		
		//return PDF::loadFile('http://www.vg.no')->stream('github.pdf');

		//return $pdf->download('dutyreport.pdf');
		
			
		
		 
		
        return view('pages.duties.perdiem.index')
        		->with('duties',$duties)
        		->with('links',$links)
        		->with('perdiems',$perdiems)
        		->with('sum_diem',$sum_diem)
        		->with('month',$month)
        		->with('year',$year)
        		->with('duty_pay',$duty_pay)
        		->with('dutiespaytypes',$dutiespaytypes)
        		->with('contact',$contact);
        		
       		
  
       }

   	public function perdiemnew($month, $year)

		{

			$user_id=Auth::user()->id;

			$period_start=Carbon::now()->year($year)->month($month)->startOfMonth();
			$period_end=Carbon::now()->year($year)->month($month)->endOfMonth();

			$duties=Duty::whereHas('per_diem', function($query) {


				$query->where('id',380);
			})->get();


			dd($duties);

						


				
       	 //return view('duties.perdiem.indexnew')->with('duties',$duties);
  
       }

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */

		public function create()
	{
		
		$per_diems=PerDiemTypes::where('parent_id',null)->get();
		$users=User::with('contact')->IsActive()->IsContact()->get();

		$airports= Airport::lists('icao','id');
		
		$duty_type=DutyType::lists('name' ,'id');

		$legs=Leg::all();

        return view('pages.duties.create')
        	->with('per_diems',$per_diems)
        	->with('duty_type',$duty_type)
        	->with('airports',$airports)
        	->with('users',$users)
        	->with('legs',$legs);
	}

/*
	public function create_overnight()

	{
		
		$per_diems= PerDiemTypes::where('parent_id',null)->get();
		
		$duty_type=DutyTypes::lists('name','id');

        return view ('duties.withovernight.create')
        	->with('per_diems',$per_diems)
        	
        	->with('duty_type',$duty_type);
	}

	public function create_perdiem_day($duty_id)

	{
		
		$duties=Duty::find($duty_id);
		$per_diems= PerDiemTypes::where('parent_id',null)->get();
		
		$duty_type=DutyTypes::lists('name','id');

        return view ('pages.duties.perdiem.singleday.create')
        	->with('duties',$duties)
        	->with('per_diems',$per_diems)
       		->with('duty_type',$duty_type);
       
	}
/*
	/**
	 * Store a newly created resource in storage. 
	 *
	 * @return Response
	 */
	public function store(DutyRequest $request)
	
	{
		

		$input=$request->all(); // get all form data

		$start_date=$input['duty_start_date'];
		$start_time=$input['duty_start_time'];
		$end_date=$input['duty_end_date'];
		$end_time=$input['duty_end_time'];

		if ($start_date && $start_time) {
			
			$start_datetime=Carbon::createFromFormat('d.m.Y H:i', "$start_date $start_time")->toDateTimeString(); // 1975-05-21 22:00:00
			$input=array_add($input, 'start_datetime', $start_datetime);
		}

		if ($end_date && $end_time) {
			
			$end_datetime=Carbon::createFromFormat('d.m.Y H:i', "$end_date $end_time")->toDateTimeString(); // 1975-05-21 22:00:00
			$input=array_add($input, 'end_datetime', $end_datetime);
		}

		if ($request->check_in_leg_id && $request->check_out_leg_id) {
		$check_in_leg=Leg::find($request->check_in_leg_id);
		$check_out_leg=Leg::find($request->check_out_leg_id);
		}

		$duty= New Duty;
		$duty->contact_id=$request->contact_id;
		$duty->created_by_user_id=Auth::user()->id;
		$duty->duty_start=$start_datetime;
		$duty->duty_end=$end_datetime;
		$duty->check_in_location_id=$request->check_in_place;
		$duty->check_out_location_id=$request->check_out_place;
		$duty->type_id=$request->duty_type;
		$duty->sectors=$request->sectors;
		$duty->flight_time=$request->flighttime;
		$duty->comment=$request->comment;
		
		

		if($check_in_leg && $check_out_leg ) {

		$duty->check_in_leg_id=$check_in_leg->id;
		$duty->check_out_leg_id=$check_out_leg->id;
		$duty->duty_start=$check_in_leg->std->subMinutes(120);
		$duty->duty_end=$check_out_leg->sta()->addMinutes(90);

		}

		$duty->save();

		

	

				//$request->session()->put('key','value');

				//return $request->session()->pull('key');

		
			//return $formdata['per_diem_type'];
	
		
		return Redirect::to('duties/' . $duty->id);
		
		
	}



	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	
	{
		//$duties=Duty::with('per_diem')->orderBy('per_diem.datefield')->findorFail($id);

		$duties=Duty::with(['per_diem' => function($query) { 
				$query->orderBy('from_date', 'ASC'); 
				}])
				->findOrFail($id);

		$legs=Leg::whereBetween('std',[$duties->duty_start,$duties->duty_end])->get();

		
		$overnight_perdiem=$duties->per_diem()->overnight()->get();
		$singleday_perdiem=$duties->per_diem()->singleday()->get();

	   return view('pages.duties.show')->with('duties',$duties)
	   									->with('singleday_perdiem',$singleday_perdiem)
	   									->with('overnight_perdiem',$overnight_perdiem)
	   									->with('legs',$legs);
	   							
	   							
	   							
	   						
	   							
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{

		$duty_type=DutyType::lists('name','id');

		$duty = Duty::findOrFail($id);

        return view ('pages.duties.edit')
        	->with('duty',$duty)
       		->with('duty_type',$duty_type);

		
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id, DutyRequest $request)
	{
		$input=$request->all(); // get all form data

		$start_date=$input['duty_start_date'];
		$start_time=$input['duty_start_time'];
		$end_date=$input['duty_end_date'];
		$end_time=$input['duty_end_time'];

		if ($start_date && $start_time) {
			
			$start_datetime=Carbon::createFromFormat('d.m.Y H:i', "$start_date $start_time")->toDateTimeString(); // 1975-05-21 22:00:00
			$input=array_add($input, 'start_datetime', $start_datetime);
		}

		if ($end_date && $end_time) {
			
			$end_datetime=Carbon::createFromFormat('d.m.Y H:i', "$end_date $end_time")->toDateTimeString(); // 1975-05-21 22:00:00
			$input=array_add($input, 'end_datetime', $end_datetime);
		}
 
		
		$duty= Duty::find($id);
		$duty->duty_start=$start_datetime;
		$duty->duty_end=$end_datetime;
		$duty->check_in_location_id=$input['check_in_place'];
		$duty->check_out_location_id=$input['check_out_place'];
		$duty->sectors=$input['sectors'];
		$duty->flight_time=$input['flighttime'];
		$duty->comment=$request->comment;

		$duty->save();

		
		return Redirect::to('duties/' . $duty->id);
		
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		
	}

}