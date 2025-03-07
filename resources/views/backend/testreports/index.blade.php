@inject('request', 'Illuminate\Http\Request')
@extends('backend.layouts.app')
@section('title', __('Test Report').' | '.app_name())
@push('before-styles')
    <link rel="stylesheet" type="text/css" href="{{asset('assets/metronic_assets/global/plugins/jquery-ui/jquery-ui.min.css')}}"/>
@endpush
@section('content')

    <div class="card">
        <div class="card-header">
            <h3 class="page-title float-left mb-0">Test Report</h3>
            @can('question_create')
                <div class="float-right">
                   <a id="order_change" 
                       class="btn btn-primary" style="color:white">Order change</a>
                    <a href="{{ route('admin.testreports.create', [
                        'course_id' => request('course_id'),
                        'test_id' => request('test_id'),
                    ]) }}"
                       class="btn btn-success">Add New</a>

                </div>
            @endcan
        </div>
        <div class="card-body table-responsive">
            <div class="row">
                <div class="col-12 col-lg-6 form-group">
                    {!! Form::label('course_id', trans('labels.backend.questions.fields.course'), ['class' => 'control-label']) !!}
                    {!! Form::select('course_id', $courses,  (request('course_id')) ? request('course_id') : old('course_id'), ['class' => 'form-control select2', 'id' => 'course_id']) !!}
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-lg-6 form-group">
                    {!! Form::label('test_id', trans('labels.backend.questions.test'), ['class' => 'control-label']) !!}
                    {!! Form::select('test_id', $tests,  (request('test_id')) ? request('test_id') : old('test_id'), ['class' => 'form-control select2', 'id' => 'test_id']) !!}
                </div>
            </div>
            <div class="d-block">
                <ul class="list-inline">
                    <li class="list-inline-item"><a href="{{ route('admin.questions.index') }}"
                                                    style="{{ request('show_deleted') == 1 ? '' : 'font-weight: 700' }}">{{trans('labels.general.all')}}</a>
                    </li>
                    |
                    <li class="list-inline-item"><a href="{{ route('admin.questions.index') }}?show_deleted=1"
                                                    style="{{ request('show_deleted') == 1 ? 'font-weight: 700' : '' }}">{{trans('labels.general.trash')}}</a>
                    </li>
                </ul>
            </div>
            <table id="myTable"
                   class="table table-bordered table-striped @if ( request('show_deleted') != 1 ) dt-select @endif ">
                <thead>
                <tr>
                    @can('question_delete')
                        @if ( request('show_deleted') != 1 )
                            <th style="text-align:center;"><input type="checkbox" class="mass" id="select-all"/></th>@endif
                    @endcan
                        <th>@lang('labels.general.sr_no')</th>
                        <th>@lang('labels.general.id')</th>
                        <th>Title</th>
                
                        @if( request('show_deleted') == 1 )
                        <th>@lang('strings.backend.general.actions')</th>
                        @else
                        <th>@lang('strings.backend.general.actions')</th>
                        @endif
                        
                </tr>
                </thead>

                <tbody id="sortable-20">

                </tbody>
            </table>
        </div>
    </div>
@stop

@push('after-scripts')
  {{-- <script type="text/javascript" src="{{asset('js/3.5.1/jquery.min.js')}}"></script> --}}

<script>

        jQuery(document).ready(function (e) {
           // QuestionEdit.init();
              $(function() {
                $('#sortable-20').sortable({
                    update: function(event, ui) {
                    }
                });
            });

            $("#order_change").on('click',function(e){
                var order_info=[], id_info=[];
                for (var i=1;i<=$("#sortable-20").children().length;i++)
                {
                
                    id_info[i-1] =$("#sortable-20 tr:nth-child("+i+")").find("td:eq(2)").text(); //id value
                    order_info[i-1] =$("#sortable-20 tr:nth-child("+i+")").find("td:eq(1)").text();// order value
                } 

                e.preventDefault();
                    $.ajax({
                        data: { "id_info":JSON.stringify(id_info)},
                        url: 'questions/order-edit',
                        type: 'get',
                        dataType: 'json',
                        complete: function(response){     
                            alert("The order is updated successfully.");
                        },
                        error: function(response){
                            console.log("error");
                        }
                    });    
            });


            var route = '{{route('admin.testreports.get_data')}}';

            @if(request('show_deleted') == 1)
                route = '{{route('admin.testreports.get_data',['show_deleted' => 1])}}';
            @endif

            @if(request('test_id') != "")
                route = '{{route('admin.testreports.get_data',['test_id' => request('test_id')])}}';
            @else
                @if(request('course_id') != "")
                    route = '{{route('admin.testreports.get_data',['course_id' => request('course_id')])}}';
                @endif
            @endif

            $('#myTable').DataTable({
                processing: true,
                serverSide: true,
                iDisplayLength: 10,
                retrieve: true,
                dom: 'lfBrtip<"actions">',
                buttons: [
                    {
                        extend: 'csv',
                        exportOptions: {
                            columns: [ 1, 2, 3]
                        }
                    },
                    {
                        extend: 'pdf',
                        exportOptions: {
                            columns: [ 1, 2, 3]
                        }
                    },
                    'colvis'
                ],
                ajax: route,
                columns: [
                        @if(request('show_deleted') != 1)
                    { "data": function(data){
                        return '<input type="checkbox" class="single" name="id[]" value="'+ data.id +'" />';
                    }, "orderable": false, "searchable":false, "name":"id" },
                        @endif
                    {data: "DT_RowIndex", name: 'DT_RowIndex', searchable: false, orderable: false},
                    {data: "id", name: 'id'},
                    {data: "title", name: 'title'},
                  
                    {data: "actions", name: "actions"},
                  
                ],
                @if(request('show_deleted') != 1)
                columnDefs: [
                    {"width": "5%", "targets": 0},
                    {"className": "text-center", "targets": [0]}
                ],
                @endif

                createdRow: function (row, data, dataIndex) {
                    $(row).attr('data-entry-id', data.id);
                },
                language:{
                    url : '{{asset('plugins/jquery-datatable/lang/'.config('app.locale').'.json')}}',
                    buttons :{
                        colvis : '{{trans("datatable.colvis")}}',
                        pdf : '{{trans("datatable.pdf")}}',
                        csv : '{{trans("datatable.csv")}}',
                    }
                }
            });

            $(document).on('change', '#test_id', function (e) {
                var course_id = $('#course_id').val();
                var test_id = $(this).val();
                window.location.href = "{{route('admin.testreports.index')}}" + "?course_id=" + course_id + "&test_id=" + test_id;
            });
            $(document).on('change', '#course_id', function (e) {
                var course_id = $(this).val();
                window.location.href = "{{route('admin.testreports.index')}}" + "?course_id=" + course_id;
            });

            @can('question_delete')
            @if(request('show_deleted') != 1)
            $('.actions').html('<a href="' + '{{ route('admin.testreports.mass_destroy') }}' + '" class="btn btn-xs btn-danger js-delete-selected" style="margin-top:0.755em;margin-left: 20px;">Delete selected</a>');
            @endif
            @endcan



        });





    </script>
@endpush