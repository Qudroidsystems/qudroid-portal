<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as FacadesDB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * create a new instance of the class
     *
     * @return void
     */
    function __construct()
    {
         $this->middleware('permission:View permission|Create permission|Update permission|Delete permission', ['only' => ['index', 'show']]);
         $this->middleware('permission:Create permission', ['only' => ['create','store']]);
         $this->middleware('permission:Update permission', ['only' => ['edit','update']]);
         $this->middleware('permission:Delete permission', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
         #page title
         $pagetitle = "Role Management";

        $data = Permission::get();
      
        return view('permissions.index', compact('data'))->with('pagetitle', $pagetitle);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Permissions are managed on the permissions page.
        return redirect()->route('permissions.index');
    }

    public function store(Request $request)
    {
         #page title
         $pagetitle = "Role Management";
         
        $this->validate($request, [
            'name' => 'required|unique:permissions,name',
        ]);

        Permission::create(['name' => $request->input('name')]);

        return redirect()->route('permissions.index')
            ->with('success', 'Permission created successfully.')->with('pagetitle', $pagetitle);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Permissions are managed on the permissions page.
        return redirect()->route('permissions.index');
    }

    public function edit($id)
    {
        // Permissions are managed on the permissions page.
        return redirect()->route('permissions.index');
    }

    public function update(Request $request, $id)
    {
         #page title
         $pagetitle = "Role Management";

        $this->validate($request, [
            'name' => 'required'
        ]);

        $permission = Permission::find($id);
        $permission->name = $request->input('name');
        $permission->save();

        return redirect()->route('permissions.index')
            ->with('success', 'Permission updated successfully.')->with('pagetitle', $pagetitle);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Permission::find($id)->delete();

        return redirect()->route('permissions.index')
            ->with('success', 'Permission deleted successfully');
    }


}
