# rencon - 蓮根

<table>
  <thead>
    <tr>
      <th></th>
      <th>Linux</th>
      <th>Windows</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <th>master</th>
      <td style="text-align:center;">
        <a href="https://travis-ci.org/tomk79/rencon"><img src="https://secure.travis-ci.org/tomk79/rencon.svg?branch=master"></a>
      </td>
      <td style="text-align:center;">
        <a href="https://ci.appveyor.com/project/tomk79/rencon"><img src="https://ci.appveyor.com/api/projects/status/2wk7okn32pmlin8w/branch/master?svg=true"></a>
      </td>
    </tr>
    <tr>
      <th>develop</th>
      <td style="text-align:center;">
        <a href="https://travis-ci.org/tomk79/rencon"><img src="https://secure.travis-ci.org/tomk79/rencon.svg?branch=develop"></a>
      </td>
      <td style="text-align:center;">
        <a href="https://ci.appveyor.com/project/tomk79/rencon"><img src="https://ci.appveyor.com/api/projects/status/2wk7okn32pmlin8w/branch/develop?svg=true"></a>
      </td>
    </tr>
  </tbody>
</table>

`rencon` は、手軽にサッと使えるブラウザベースの簡易サーバー管理ツールです。

PHPスクリプト 1ファイルの構成で、使いたいときにすぐに使え、使い終わったら容易に削除してしまえます。
GUIベースのウェブアプリケーションなので、コマンド操作に慣れていない方でも

提供される


## インストール手順 - Install

`dist` ディレクトリにある [rencon.php](./dist/rencon.php)を、
お使いのウェブサーバーのドキュメントルート以下の任意の場所に配置して、
ウェブブラウザからアクセスします。

### 例: 手動で設置する場合

- [リリースの一覧ページ](https://github.com/tomk79/rencon/releases) から、最新のバージョンの `rencon.php` をダウンロードする。
- テキストエディタで開き、適宜設定を書き換える。(任意)
- FTPツールなどを使い、お使いのお使いのウェブサーバーのドキュメントルート以下の任意の場所にアップロードする。
- ウェブブラウザからアクセスする。


### 例: `curl` コマンドを使う場合

```
$ cd /path/to/your/htdocs/(foo)/(bar);
$ curl https://raw.githubusercontent.com/tomk79/rencon/master/dist/rencon.php -o rencon.php;
```

テキストエディタで開き、適宜設定を書き換えます。(任意)

```
$ vi rencon.php
```


## 設定する - Configuration

`rencon.php` をテキストエディタで開くと、先頭付近には、

```php
// =-=-=-=-=-=-=-=-=-=-=-= Configuration START =-=-=-=-=-=-=-=-=-=-=-=
$conf = new stdClass();
```

で始まる設定ゾーンが記述されています。

`$conf` の値を書き換えて、rencon の機能を制御することができます。


### ログインユーザー設定

rencon の初期画面は、ログイン画面から始まります。
`$conf->users` に 登録されたユーザーが、ログインを許可されます。
ユーザーIDを キー に、sha1ハッシュ化されたパスワード文字列を 値 に持つ連想配列で設定してください。
ユーザーは、複数登録できます。

```php
$conf->users = array(
	"admin" => sha1("admin"),
);
```

ユーザーを登録しない場合は、ログインなしで使用できます。
そうしたい場合は、`$conf->users` を `null` に設定してください。

```php
$conf->users = null;
```

### データベース接続情報設定

この説明は準備中です。

```php
$conf->databases = array(
  /*  */
);
```



## システム要件 - System Requirement

- PHP 5.4+ が動作するウェブサーバー
- PDO が有効なこと。
- mbstring が有効なこと。


## 更新履歴 - Change log

### rencon v0.0.1 (リリース日未定)

- Initial Release.


## 開発者向け情報 - for Developer

### ビルド - Build

```
$ cd {$projectRoot}
$ php build/build.php
```

### テスト - Test

```
$ cd {$projectRoot}
$ php vendor/phpunit/phpunit/phpunit
```


## ライセンス - License

MIT License


## 作者 - Author

- Tomoya Koyanagi <tomk79@gmail.com>
- website: <https://www.pxt.jp/>
- Twitter: @tomk79 <https://twitter.com/tomk79/>
