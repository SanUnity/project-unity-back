<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/* USERS */
Route::get("/users/session", 'UserController@session');
Route::post("/users/signup", 'UserController@signup');
Route::post("/users/validate", 'UserController@validateOTP');
Route::post("/users/signup/anonymous", 'UserController@signupAnonymous');
Route::post("/users/logout", 'UserController@logout');
Route::post("/users/state", 'UserController@setStateUser');

Route::post("/users/profiles", 'UserController@createProfile');
Route::get("/users/profiles/{profileID}", 'UserController@getProfile');
Route::put("/users/profiles/{profileID}", 'UserController@editProfile');
Route::delete("/users/profiles/{profileID}", 'UserController@removeProfile');
Route::post("/users/profiles/{profileID}/main", 'UserController@setDefaultProfile');

Route::post("/users/profiles/{profileID}/tests", 'UserController@testsNew');
Route::get("/users/profiles/{profileID}/tests", 'UserController@getTests');

Route::post("/users/devicetoken", 'UserController@devicetoken');
Route::post("/devicetoken", 'UserController@anonymousDevicetoken');

Route::get("/postalCodes/{postalCode}", 'UserController@postalCode');

Route::get("/states", 'UserController@states');
Route::get("/states/{stateID}/municipalities", 'UserController@municipalities');

Route::get("/states/{stateID}/municipalities/{municipalityID}/hospitals", 'UserController@hospitals');

Route::post("/users/error", 'UserController@saveError');

if(config('modules.exitRequests') || config('app.env') === 'testing'){
    Route::post("/users/profiles/{profileID}/exitRequests", 'UserController@createExitRequest');
    Route::delete("/users/profiles/{profileID}/exitRequests/{exitRequestID}", 'UserController@deleteExitRequest');
}

if(config('modules.bluetrace') || config('app.env') === 'testing'){
    Route::get("/users/bluetrace/tempIDs", 'UserController@bluetraceTempIDs');
    Route::post("/users/bluetrace", 'UserController@bluetraceData');
}

if(config('modules.dp3t') && !config('modules.exposureNotification') && config('app.env') !== 'testing'){
    Route::post("/exposed", 'UserController@saveExposedDP3T');
    Route::post("/exposed/end", 'UserController@saveExposedEndDP3T');
    Route::get("/exposed/{batchReleaseTime}", 'UserController@getExposedByBatchReleaseTimeDP3T');
}else if(config('app.env') === 'testing'){
    Route::post("/exposed/DP3T", 'UserController@saveExposedDP3T');
    Route::post("/exposed/DP3T/end", 'UserController@saveExposedEndDP3T');
    Route::get("/exposed/DP3T/{batchReleaseTime}", 'UserController@getExposedByBatchReleaseTimeDP3T');
}

if((config('modules.exposureNotification') && !config('modules.dp3t')) || config('app.env') === 'testing'){
    Route::post("/exposed", 'UserController@saveExposed');
    Route::get("/exposed/config", 'UserController@getExposedConfig');
    Route::get("/exposed/{batchReleaseTime}", 'UserController@getExposedByBatchReleaseTime');
    Route::post("/exposed/contact", 'UserController@saveExposedContact');
}

if(config('modules.pcr') || config('app.env') === 'testing'){
    Route::post("/users/profiles/{profileID}/pcr", 'UserController@createDataPCR');
    Route::post("/users/profiles/{profileID}/pcr/{pcrID}/validate", 'UserController@verifyOTPPCR');
    Route::put("/users/profiles/{profileID}/pcr", 'UserController@editDataPCR');
    Route::post("/users/profiles/{profileID}/pcr/{pcrID}/readed", 'UserController@readedPCR');
    Route::post("/users/profiles/{profileID}/pcr/{pcrID}/notify", 'UserController@notifyPCR');
    Route::get("/users/centers/{centerID}/validate", 'UserController@getValidateCenter');
}

if(config('modules.semaphore') || config('app.env') === 'testing'){
    Route::post("/users/semaphore/fav", 'UserController@setSemaphoreFav');
    Route::get("/states/info", 'UserController@getStatesInfo');
    Route::get("/municipalities/info", 'UserController@getMunicipalitiesInfo');
}


/* BACK */
Route::post("/backend/devicetoken", 'BackendController@devicetoken');


/* ADMINS */
Route::get("/admins/session", 'AdminController@session')->middleware('logAdmin');
Route::post("/admins/signin", 'AdminController@signin')->middleware('logAdmin');
Route::post("/admins/logout", 'AdminController@logout')->middleware('logAdmin');

Route::get("/admins/states", 'AdminController@states')->middleware('logAdmin');
Route::get("/admins/states/{stateID}/municipalities", 'AdminController@municipalities')->middleware('logAdmin');
Route::get("/admins/states/{stateID}/municipalities/{municipalityID}/suburbs", 'AdminController@suburbs')->middleware('logAdmin');

Route::get("/admins/stats/age", 'AdminController@age')->middleware('logAdmin');
Route::get("/admins/stats/gender", 'AdminController@gender')->middleware('logAdmin');
Route::get("/admins/stats/timeline", 'AdminController@timeline')->middleware('logAdmin');

Route::get("/admins/tests", 'AdminController@tests')->middleware('logAdmin');
Route::get("/admins/users/{profileID}", 'AdminController@userDetail')->middleware('logAdmin');

Route::get("/admins/states/registers", 'AdminController@statesRegisters')->middleware('logAdmin');
Route::get("/admins/states/{stateID}/registers", 'AdminController@municipalitiesRegisters')->middleware('logAdmin');
Route::get("/admins/states/{stateID}/municipalities/{municipalityID}/registers", 'AdminController@suburbsRegisters')->middleware('logAdmin');

Route::get("/admins/states/tests", 'AdminController@statesTests')->middleware('logAdmin');
Route::get("/admins/states/{stateID}/tests", 'AdminController@municipalitiesTests')->middleware('logAdmin');
Route::get("/admins/states/{stateID}/municipalities/{municipalityID}/tests", 'AdminController@suburbsTests')->middleware('logAdmin');

Route::get("/admins/states/trends/{trendType}", 'AdminController@statesTrends')->middleware('logAdmin');
Route::get("/admins/states/{stateID}/trends/{trendType}", 'AdminController@municipalitiesTrends')->middleware('logAdmin');
Route::get("/admins/states/{stateID}/municipalities/{municipalityID}/trends/{trendType}", 'AdminController@suburbsTrends')->middleware('logAdmin');

Route::get("/admins/hospitals/tests", 'AdminController@hospitals')->middleware('logAdmin');
Route::get("/admins/hospitals/stats", 'AdminController@hospitalsStats')->middleware('logAdmin');
Route::get("/admins/states/hospitalsTests", 'AdminController@statesHospitalsTests')->middleware('logAdmin');
Route::get("/admins/states/{stateID}/hospitalsTests", 'AdminController@municipalitiesHospitalsTests')->middleware('logAdmin');
Route::get("/admins/states/{stateID}/municipalities/{municipalityID}/hospitalsTests", 'AdminController@suburbsHospitalsTests')->middleware('logAdmin');
Route::post("/admins/hospitals/tests", 'AdminController@createHospitalsTests')->middleware('logAdmin');
Route::delete("/admins/hospitals/{hospitalID}", 'AdminController@deleteHospital')->middleware('logAdmin');
Route::put("/admins/hospitals/{hospitalID}/tests/{testID}", 'AdminController@editHospitalsTests')->middleware('logAdmin');
Route::delete("/admins/hospitals/{hospitalID}/tests/{testID}", 'AdminController@deleteHospitalsTests')->middleware('logAdmin');

Route::post("/admins/states/{stateID}/message", 'AdminController@sendMessageState')->middleware('logAdmin');
Route::post("/admins/states/{stateID}/municipalities/{municipalityID}/message", 'AdminController@sendMessageMunicipality')->middleware('logAdmin');
Route::post("/admins/states/{stateID}/municipalities/{municipalityID}/suburbs/{suburbID}/message", 'AdminController@sendMessageSuburb')->middleware('logAdmin');
Route::post("/admins/users/{profileID}/message", 'AdminController@sendMessageUser')->middleware('logAdmin');
Route::post("/admins/message", 'AdminController@sendMessageAllUser')->middleware('logAdmin');

Route::get("/admins", 'AdminController@getAdmins')->middleware('logAdmin');
Route::post("/admins/invite", 'AdminController@createAdmin')->middleware('logAdmin');
Route::put("/admins/{adminID}", 'AdminController@editAdmin')->middleware('logAdmin');
Route::delete("/admins/{adminID}", 'AdminController@deleteAdmin')->middleware('logAdmin');
Route::post("/admins/password", 'AdminController@setPassword')->middleware('logAdmin');
Route::post("/admins/resetPassword", 'AdminController@resetPassword')->middleware('logAdmin');

if(config('modules.semaphore') || config('app.env') === 'testing'){
    Route::get("/admins/states/info", 'AdminController@getStatesInfo')->middleware('logAdmin');
    Route::put("/admins/states/{stateID}/info", 'AdminController@setStatesInfo')->middleware('logAdmin');
    Route::get("/admins/municipalities/info", 'AdminController@getMunicipalitiesInfo')->middleware('logAdmin');
    Route::put("/admins/municipalities/{municipalityID}/info", 'AdminController@setMunicipalitiesInfo')->middleware('logAdmin');
    Route::get("/admins/states/info/file", 'AdminController@downloadStatesInfo')->middleware('logAdmin');
    Route::get("/admins/municipalities/info/file", 'AdminController@downloadMunicipalitiesInfo')->middleware('logAdmin');
    Route::post("/admins/states/info/file", 'AdminController@updloadStatesInfo')->middleware('logAdmin');
    Route::post("/admins/municipalities/info/file", 'AdminController@updloadMunicipalitiesInfo')->middleware('logAdmin');
}

Route::get("/stats/ageGender/{type}", 'AdminController@statsAgeGenderType')->middleware('logAdmin');
Route::get("/stats/symptoms/{type}", 'AdminController@statsSymptoms')->middleware('logAdmin');
Route::get("/stats/socialSecurity/{type}", 'AdminController@statsSocialSecurity')->middleware('logAdmin');
Route::get("/stats/geo", 'AdminController@statsGeo')->middleware('logAdmin');
Route::get("/stats/indicators", 'AdminController@statsIndicators')->middleware('logAdmin');
Route::get("/stats/states/{type}", 'AdminController@statsStates')->middleware('logAdmin');
Route::get("/stats/states/{stateID}/municipalities/{type}", 'AdminController@statsMunicipalities')->middleware('logAdmin');
Route::get("/stats/states/{stateID}/municipalities/{municipalityID}/suburbs/{type}", 'AdminController@statsSuburbs')->middleware('logAdmin');
Route::get("/stats/cases/{interval}", 'AdminController@statsCases')->middleware('logAdmin');
Route::get("/stats/age", 'AdminController@statsAge')->middleware('logAdmin');
Route::get("/stats/or/{type}/{stateID}", 'AdminController@statsOddsRatio')->middleware('logAdmin');

if((config('modules.exposureNotification') && !config('modules.dp3t')) || config('app.env') === 'testing'){
    Route::get("/admins/en/stats", 'AdminController@enGetStats')->middleware('logAdmin');
    Route::get("/admins/en/config", 'AdminController@enGetConfig')->middleware('logAdmin');
    Route::put("/admins/en/config", 'AdminController@enSetConfig')->middleware('logAdmin');
}


Route::get("/admins/otps", 'AdminController@getOtpsData')->middleware('logAdmin');
Route::get("/admins/tests/file", 'AdminController@downloadTests')->middleware('logAdmin');

if(config('modules.pcr') || config('app.env') === 'testing'){
    Route::post("/admins/pcr/result", 'AdminController@setPcrResult')->middleware('logAdmin');
}