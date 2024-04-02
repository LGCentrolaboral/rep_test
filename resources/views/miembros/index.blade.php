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
            <div class="top-bar">
                <h2>Simular tramite</h2>
                <label for="tipoTramite">Selecciona el tipo de tramite:</label>
                <select class="" name="tipoTramite" id="tipo_tramite">
                    <option value="0">Selecciona un tramite</option>
                </select>
                <br>
                <br>
                <button id="simularTramite" class="block text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" type="button">
                    Crear tramite
                </button>
                <hr>
                <div id="datos_tramite"></div>
            </div>
            <hr>
            <div>
                <br>
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
    /////Validar campos vacios al cargar el documento
        //// Validar respuesta de las curps
            //// Validar curps con renapo para la obtencion del estado de la curp segun la documentación
                /// Reflejar estado en la vista del tramite
                    /// Realizar acciones desde el panel para la curp
                        /// Almacenar el estado de las curps si se interrumpe la operacion ( Base de datos [ json o registro por registro ] o localstorage arreglo de operaciones)

    function iniciarTramite(token, tipo_tramite){
        
        csrfToken = token;
        $nombre_tramite = 
        $.ajax({
                url:    '/tramites',
                method: 'POST',
                headers:{
                    'X-CSRF-TOKEN': csrfToken
                },
                data:  { 'id_tipo_tramite': tipo_tramite },
                success: function(response){
                    console.log(response);
                    $('#datos_tramite').empty();
                    $('#datos_tramite').html(
                        '<h1> Tramite Seleccionado: ' + $('#tipo_tramite option:selected').text() + '</h1><br><h1>ID del tramite: ' + response.id + '</h1>'
                    );

                    return response;
                },
                statusCode: {
                    404:(response)=>{
                        console.error('No se encontro la ruta', response);
                        return response;
                    },
                    500:(response)=>{
                        console.error('Error en el servidor', response);
                        return response;
                    }
                }
            });

        

    }


    function getData(token){

        csrfToken = token; 
        
     

        $.ajax({
            url:    '/datosTramites',
            method: 'POST',
            headers:{
                        'X-CSRF-TOKEN': csrfToken
                    },
            data:   {  },
            success: function(response){
                response.forEach((row)=>{
                    $('#tipo_tramite').append('<option value=' + row.id + '>' + row.descripcion + '</option>');
                });   
            },
            statusCode: {
              404: (response)=>{
                console.error('No se encontro la ruta', response);
              },
              500: (response)=>{
                console.error('Error en el servidor', response);
              }  
            }
        });
    }

    $(document).ready(()=>{

        
        var tramite;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        $('#simularTramite').click(()=>{
            tipo_tramite = $('#tipo_tramite').val();
            console.log(csrfToken);
           tramite =  iniciarTramite(csrfToken,tipo_tramite);
           

        });

        getData(csrfToken);


        $('#tipo_tramite').change(()=>{
            console.log($('#tipo_tramite').val());
        });

        // Traer datos de la bd de registro
      

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
                    miembros:       row,
                    tipo_tramite:   tramite
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



