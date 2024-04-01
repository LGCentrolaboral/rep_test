<meta name="csrf-token" content="{{ csrf_token() }}">

<x-app-layout>
    <link rel="stylesheet" href="{{ Vite::asset('resources/css/carga_miembros.css') }}">
      <!-- Scripts -->
      <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Test Carga de miembros
        </h2>
    </x-slot>

    <div class="contenedor">
        <div class="data_contenedor">
            <div>
                <h2>Adjuntar archivo</h2>
                <hr>
                <br>
                <form action="#">
                    <input class="form-control" type="file" id="list_miembros">
                    <br>
                    <br>
                    <button id="loadMiembros" class="block text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" type="button">
                        Validar miembros
                    </button>

                
                </form>
            </div>
            <div class="vista-data-cargada">
                <ul id="csvValues"></ul>
            </div>
            <br>
            <h1>Logs de la API</h1>
            <button id="showPromises">Ver resultado Promesas</button>
            <div class="log-api"></div>
        </div>
    </div>


    <!-- Open the modal using ID.showModal() method -->
    <div id="modal-load">
        <div class="window-status">
            <p>Validando miembros, esta acción puede llevar varios minutos dependiendo el número de miembros cargados...</p>
            <div id="status_load">
                <div id="total"></div>
                <div id="notification"></div>
                <div id="status-progress">
                    <div id="loader-bar">
                        <div id="progress-bar"></div>
                    </div>
                    <div id="porcent"></div>
                </div>
                
            </div>
        </div>
    
    </div>
    

</x-app-layout>

<script>
    $(document).ready(()=>{

        $("#csvValues li").click(function (event) {
            console.log($(this).text());
        });

        // $('#csvValues').on('click','li',()=>{
        //     console.log($(this).text());
        // })

        $('#csvValues').on('click', '.btn-deleted',()=>{
            console.log('test');
            const textoLi = $(this).children('li').text();
            console.log('Texto dentro del <li>:', textoLi);
            console.log($('#csvValues li' + $(this)).text());
            //console.log($(this).val());
            
        });

        $('.btn-deleted').click(()=>{
            console.log("Se borrara el registro");
        });


        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        $('#showPromises').click(()=>{
            $('.log-api').empty();
            $('.log-api').html(JSON.stringify(resultPromises));
        });

        var miembros = [];
        var csvContent = [];
        var resultPromises = [];
        var sizeCsvContent = 0;

        $('#list_miembros').on('change',(event)=>{
            const file = event.target.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {

                const lines = e.target.result.split('\n');

                lines.forEach(function(line){
                    const values = line.split(',');
                    const csvObject = {
                        'nombre' : values[0],
                        'primerApellido' : values[1],
                        'segundoApellido' : values[2],
                        'curp' : values[3],
                        'empresa' : values[4],

                    }
                    sizeCsvContent = sizeCsvContent + 1;

                    console.log(sizeCsvContent);
                    
                    csvContent.push(csvObject);
                });

                const csvList = $('#csvValues');
                csvList.empty();
                csvContent.forEach((row)=>{
                    const listItem = $('<li>');

                        // Crear el div con ID 'status'
                        const statusDiv = $('<div>').attr('id', 'status').text('Estado: Pendiente');

                        const editMiembro = $('<button class="btn-deleted" id="deleted-item"> Eliminar registro </button>');

                        // Agregar el texto del campo 'curp' al elemento <li>
                        listItem.text('CURP: ' + JSON.stringify(row['curp']));

                        // Agregar el div 'status' al elemento <li>
                        listItem.append(statusDiv);

                        //Agregamos el boton

                        listItem.append(editMiembro);

                        // Agregar el elemento <li> a la lista
                        csvList.append(listItem);


                });

                // console.log(JSON.stringify(csvContent));
            };

            miembros = csvContent;

            //console.log(sizeCsvContent);

            reader.readAsText(file);
        });


        $('#loadMiembros').click((e)=>{
                e.preventDefault();
                var pointer = 0;
                var promises = [];
                var promise;
                var actual_progress = 0;

                sizeCsvContent = sizeCsvContent - 1;

                var next_progress = 100/sizeCsvContent;

                $('#modal-load').show();
                $('#total').html('total de miembros cargados: ' + sizeCsvContent);
                $('#notification').html('Validando miembro: ' + pointer + ' de ' + sizeCsvContent );
                //console.log(miembros[0]);
                miembros.forEach((row)=>{

                    if(row.curp === undefined ){
                        return;
                    }

                    const dataToSend = {
                    miembros: row
                    };

                    promise = fetch('/validarMiembro', {
                    method: "POST",
                    body: JSON.stringify(dataToSend),
                    headers: {
                        "Content-type": "application/json; charset=UTF-8",
                        'X-CSRF-TOKEN': csrfToken
                        }
                    }).then((response)=>{
                        if(!response.ok){
                            throw new Error('Error en la solicitud.');
                        }
                        return response.json();
                    }).then((data)=>{
                        console.log("Correcto", data);
                        resultPromises.push(data);
                        pointer = pointer + 1;
                        console.log(pointer);
                        $('#notification').html('Validando miembro: ' + pointer + ' de ' + sizeCsvContent );
                        actual_progress = actual_progress + next_progress;
                        $('#progress-bar').width(actual_progress + '%');
                        $('#porcent').empty();
                        $('#porcent').html(Math.round(actual_progress) + '%');
                        return data;
                    }).catch((error)=>{
                        console.log("Hubo un error! " + error +  ' CURP: ' + JSON.stringify(row)  );
                        console.log(pointer);
                        $('#notification').html('Validando miembro: ' + pointer + ' de ' + sizeCsvContent );
                        actual_progress = actual_progress + next_progress;
                        $('#progress-bar').css('width', actual_progress + '%');
                        $('#porcent').empty();
                        $('#porcent').html(Math.round(actual_progress) + '%');
                        resultPromises.push(data);
                        throw error;
                    });

                    console.log(promise);

                    promises.push(promise);



                }); // END foreach

                Promise.all(promise)
                    .then((result)=>{
                        console.log("Todas las promesas recibidas" , result);
                       

                    })
                    .catch((error)=>{
                        console.error("Error en la solicitud", error);
                        
                    });
        });

    });
</script>



