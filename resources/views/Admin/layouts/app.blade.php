<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>JFC management System</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  @include('Admin.layouts.style')

  <!-- =======================================================
  * Template Name: NiceAdmin
  * Updated: Jan 29 2024 with Bootstrap v5.3.2
  * Template URL: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
</head>

<body>
<!-- header -->
@include('Admin.layouts.header')
@include('Admin.layouts.navbar')

@yield('content')
 
  
<!-- footer -->
@include('Admin.layouts.footer')

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

 <!-- js file -->
 @include('Admin.layouts.scripts')

</body>

</html>