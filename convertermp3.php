<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dividir e Converter Arquivo de Áudio</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lamejs/1.2.0/lame.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.5.0/jszip.min.js"></script>
    <style>
        body {
            background-color: white; /* White background */
            color: #ffffff; /* Blue text */
            font-family: Arial, sans-serif; /* Modern font */
            text-align: center; /* Center align text */
            margin: 0;
            padding-top: 150px; /* Top margin */
        }
        .container {
            width: 750px; /* Set the width */
            margin: 0 auto; /* Center the container */
            border: 2px solid #007bff; /* Blue border */
            border-radius: 10px; /* Rounded corners */
            padding: 20px; /* Padding inside the container */
            background-color: #4682B4	; /* Cyan background */
        }
        h1 {
            margin-top: 20px;
        }
        input, button, progress {
            margin-top: 10px;
        }
        #downloadLinks {
            margin-top: 20px;
        }
        button, input[type="file"] {
            cursor: pointer;
        }
        button {
            background-color: white;
            color: #007bff;
            border: 1px solid #007bff;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 1em;
        }
        button:hover {
            background-color: #007bff;
            color: white;
        }
        progress {
            width: 100%;
            height: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Dividir e Converter Arquivo de Áudio para MP3</h1>

    <input type="file" id="audioFileInput" accept="audio/*"><br>
    <button onclick="prepareAndStart()">Dividir e Converter</button>

    <div id="downloadLinks">
    </div>
</div>

<script>
let isCancelled = false;
const MAX_DURATION_MINUTES = 28; // Duração máxima atualizada para 28 minutos

async function prepareAndStart() {
    const downloadLinksDiv = document.getElementById('downloadLinks');
    downloadLinksDiv.innerHTML = '';

    const cancelButton = document.createElement('button');
    cancelButton.innerHTML = 'Cancelar';
    cancelButton.addEventListener('click', () => {
        isCancelled = true;
    });
    downloadLinksDiv.appendChild(cancelButton);

    const progressBar = document.createElement('progress');
    progressBar.value = 0;
    progressBar.max = 100;
    downloadLinksDiv.appendChild(progressBar);

    const errorDiv = document.createElement('div');
    errorDiv.style.display = 'none';
    downloadLinksDiv.appendChild(errorDiv);

    divideAndConvertAudio(progressBar, errorDiv);
}

async function divideAndConvertAudio(progressBar, errorDiv) {
    const audioFileInput = document.getElementById('audioFileInput');
    const audioFile = audioFileInput.files[0];

    const audioContext = new AudioContext();
    const reader = new FileReader();

    reader.onload = async (event) => {
        const audioBuffer = await audioContext.decodeAudioData(event.target.result);
        const zip = new JSZip();
        
        const chunkSize = MAX_DURATION_MINUTES * 60; // Max duration in seconds
        const numParts = Math.ceil(audioBuffer.duration / chunkSize);

        if (numParts === 1 && !isCancelled) {
            // If the audio file fits in one segment, save it directly as MP3
            const slice = audioBuffer.getChannelData(0);
            const samples = Int16Array.from(slice.map(n => n * 32767));

            const mp3encoder = new lamejs.Mp3Encoder(1, audioBuffer.sampleRate, 128);
            let mp3Data = [];
            let mp3buf = mp3encoder.encodeBuffer(samples);
            if (mp3buf.length > 0) {
                mp3Data.push(mp3buf);
            }

            mp3buf = mp3encoder.flush();  // flush any remaining data left in the encoder
            if (mp3buf.length > 0) {
                mp3Data.push(mp3buf);
            }

            const blob = new Blob(mp3Data, {type: 'audio/mp3'});
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'audio.mp3';
            link.innerHTML = 'Baixar MP3';
            document.getElementById('downloadLinks').appendChild(link);
        } else {
            // If the audio file needs to be split, proceed with dividing and converting
            for (let i = 0; i < numParts; i++) {
                if (isCancelled) {
                    errorDiv.innerHTML = "Operação cancelada.";
                    errorDiv.style.display = 'block';
                    return;
                }

                const startOffset = Math.floor(i * chunkSize * audioBuffer.sampleRate);
                const endOffset = Math.min(Math.floor((i + 1) * chunkSize * audioBuffer.sampleRate), audioBuffer.length);

                const slice = audioBuffer.getChannelData(0).slice(startOffset, endOffset);
                const samples = Int16Array.from(slice.map(n => n * 32767));

                const mp3encoder = new lamejs.Mp3Encoder(1, audioBuffer.sampleRate, 128);
                let mp3Data = [];
                let mp3buf = mp3encoder.encodeBuffer(samples);
                if (mp3buf.length > 0) {
                    mp3Data.push(mp3buf);
                }

                mp3buf = mp3encoder.flush();  // flush any remaining data left in the encoder
                if (mp3buf.length > 0) {
                    mp3Data.push(mp3buf);
                }

                zip.file(`segment-${i}.mp3`, new Blob(mp3Data, {type: 'audio/mp3'}));

                progressBar.value = (i + 1) / numParts * 100;
            }

            zip.generateAsync({ type: "blob" }).then(function(blob) {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'partes.zip';
                link.innerHTML = 'Baixar ZIP com segmentos';
                document.getElementById('downloadLinks').appendChild(link);
            });
        }
    };

    reader.readAsArrayBuffer(audioFile);
}
</script>
</body>
</html>
