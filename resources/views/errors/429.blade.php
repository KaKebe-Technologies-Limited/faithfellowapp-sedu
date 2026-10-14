@extends('errors.layout')

@section('code', '429')
@section('title', __("Too many requests"))
@section('message', __("You're going a little fast. Please wait a moment and try again."))
