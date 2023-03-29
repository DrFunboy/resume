import express from 'express';
import gm from "gm";
import AWS from "aws-sdk";

const app = express();

var endpoint = "https://storage.yandexcloud.net";
var bucket = "scrm";

AWS.config.update({ //Настройки подлючения
    accessKeyId: "qNE6IX8R4VRST5IMogSK",
    secretAccessKey: "lkWpY-Ds5J8wH2r1JORIfYnafEMPBqrOZMiXtKWC",
    version: 'latest',
    endpoint: endpoint,
    region: 'ru-central1',
});
var s3 = new AWS.S3();

var thumbDir = '_thumb'; //Папка куда будут сохраняться превью
var thumbSizes = {
    sm: {
        f: 'jpeg',
        q: 80,
        w: 400,
        h: 400,
        zc: 'Center',
        ar: 'x'
    },
    md: {
        f: 'jpeg',
        q: 75,
        w: 700,
        ar: 'x'
    },
    logo: {
        f: 'jpeg',
        q: 100,
        w: 350
    }
};

app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.get("/", async (req, res) => {
    var dest = req.query.path;
    if ( dest ) {
        dest = dest.split(".");
        var ext = dest.pop();
        var size = dest.pop();
        var origext = dest[dest.length-1].toLowerCase();
        dest = dest.join('.');
        var url = `${endpoint}/${bucket}/${dest}`; //Оригинальная картинка
        s3.getObject({
            Bucket: bucket,
            Key: dest
        }, function(err, data) {
            if (err) {
                res.writeHead(302, {
                    'Location': "https://storage.yandexcloud.net/scrm/no-image.png"
                });
                res.end();
            }
            var im = gm.subClass({ imageMagick:'7+'});
            // console.log("origext", origext);
            // if ( origext == "heic" || origext == "heif") {
            //     im = gm.subClass({ imageMagick:'7+'});
            // } else {
            //     im = gm;
            // }

            im(data.Body)
            .setFormat("jpeg")
            .resize(thumbSizes[size].w, thumbSizes[size].h || null, "^")//Обрезание, изначально сохраняет пропорции
            .quality(thumbSizes[size].q)//Качество jpeg
            .gravity(thumbSizes[size].zc || null)//Центрирование обрезания
            .extent(thumbSizes[size].w, thumbSizes[size].h || null)//Вставка на холст, нужна что бы изменить пропорции, при необходимости
            .stream(function(err, stdout, stderr){
                s3.upload({
                    Bucket: bucket,
                    Key: `${thumbDir}/${req.query.path}`,
                    ContentType: "image/jpeg",
                    Body: stdout
                }, function(err, sres) {
                    res.set('Content-Type', 'image/jpeg');
                    stdout.pipe(res);
                });
            });
        });
    }
});

app.listen(process.env.PORT, () => {
    //console.log(`App listening at port ${process.env.PORT}`);
});
