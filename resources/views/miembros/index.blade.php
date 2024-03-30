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
                    <button class="btn " id="loadMiembros">Validar miembros</button>
                </form>
            </div>
            <div class="vista-data-cargada">
                <ul id="csvValues"></ul>
            </div>
        </div>
    </div>


</x-app-layout>

<script>
    $(document).ready(()=>{
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');



        var miembros = [];
        var csvContent = [];

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
                    csvContent.push(csvObject);
                });

                const csvList = $('#csvValues');
                csvList.empty();
                csvContent.forEach((row)=>{
                    const listItem = $('<li>');

                        // Crear el div con ID 'status'
                        const statusDiv = $('<div>').attr('id', 'status').text('Estado: Pendiente');

                        // Agregar el texto del campo 'curp' al elemento <li>
                        listItem.text('CURP: ' + JSON.stringify(row['curp']));

                        // Agregar el div 'status' al elemento <li>
                        listItem.append(statusDiv);

                        // Agregar el elemento <li> a la lista
                        csvList.append(listItem);
                });

                // console.log(JSON.stringify(csvContent));
            };

            miembros = csvContent;

            reader.readAsText(file);
        });


        $('#loadMiembros').click((e)=>{
                e.preventDefault();

                var promises = [];
                var promise;

                console.log(miembros[0]);


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
                        // response.json().then((object)=>{
                        //     console.log("Correct!", object);
                    }).then((data)=>{
                        console.log("Correcto", data);
                        return data;
                    }).catch((error)=>{
                        console.log("Hubo un error! " + error +  ' CURP: ' + JSON.stringify(row)  );
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



