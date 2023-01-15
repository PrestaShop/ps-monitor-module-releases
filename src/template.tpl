<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>PrestaShop modules release status</title>

    <!-- Bootstrap core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" integrity="sha384-V05SibXwq2x9UKqEnsL0EnGlGPdbHwwdJdMjmp/lw3ruUri9L34ioOghMTZ8IHiI" crossorigin="anonymous">

    <script src="https://code.jquery.com/jquery-3.5.1.js" integrity="sha384-/LjQZzcpTzaYn7qWqRIWYC5l8FWEZ2bIHIz0D73Uzba4pShEcdLdZyZkI4Kv676E" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js" integrity="sha384-ZuLbSl+Zt/ry1/xGxjZPkp9P5MEDotJcsuoHT0cM8oWr+e1Ide//SZLebdVrzb2X" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js" integrity="sha384-jIAE3P7Re8BgMkT0XOtfQ6lzZgbDw/02WeRMJvXK3WMHBNynEx5xofqia1OHuGh0" crossorigin="anonymous"></script>
    <script type="text/javascript" class="init">
      $(document).ready(function () {
        $('#modules').DataTable( {
          paging: false,
          order: [[3, 'desc']],
        } );
      });      
    </script>
  </head>

  <body>

    <div class="d-flex flex-column flex-md-row align-items-center p-3 px-md-4 mb-3 bg-white border-bottom box-shadow">
      <h5 class="my-0 mr-md-auto font-weight-normal">PrestaShop modules release status</h5>
    </div>

    <div class="pricing-header px-3 py-3 pt-md-5 pb-md-4 mx-auto text-center">
      <h1 class="display-4">Modules</h1>
      <p class="lead">Static HTML page that displays monitored modules git status. Built on GitHub Actions, GitHub pages on <a href="https://github.com/PrestaShop/ps-monitor-module-releases">PrestaShop/ps-monitor-module-releases</a></p>
      <span class="badge bg-primary">Latest update: {%%latestUpdateDate%%}</span>
    </div>

    <div class="container-fluid">

      {%%notifications%%}

      <table class="table table-striped table-bordered" id="modules">
        <thead>
          <tr>
            <th scope="col">#</th>
            <th scope="col">Module name</th>
            <th scope="col">Need release?</th>
            <th scope="col">Commits ahead</th>
            <th scope="col">Last release</th>
            <th scope="col">Last release information</th>
            <th scope="col">Next release information</th>
          </tr>
        </thead>
        <tbody>
          {%%tableBody%%}
        </tbody>
      </table>
    </div>
  </body>
</html>
