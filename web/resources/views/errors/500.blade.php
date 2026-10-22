@extends('errors.layout')

@section('code', '500')
@section('title', __("Something went wrong"))
@section('message', __("Our team has been notified. Please try again in a moment.").(\App\Support\ServerErrors::reference() ? ' '.__('Reference: :ref.', ['ref' => \App\Support\ServerErrors::reference()]) : ''))
