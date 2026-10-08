#!/usr/bin/env bash
# Execute in the cPanel Terminal as veneza. No passwords or database changes.
set -Eeuo pipefail

release=/home/veneza/private-release-20261007-d207da3
public=/home/veneza/public_html
old=/home/veneza/private-cutover-20261007-d207da3/old-main
expected_zip=f6cd07fccae4e6be2f29e559ab00bac43be715138cfd884c948d01d851d140e8
expected_htaccess=b4c7db3effc78af30115f0cafaf1fff03b68c04253c40068c23e2409e13099ec
new=(article.php artigo.html conhecimento.html contato.html produtos.html projetos.html solucoes.html veneza.html lib conhecimento assets admin)
legacy=(index.php index-antigo.php gerador_xml.php sobre.php test.php php phpmailer include blog blog-old cgi-bin css fonts javascript novo-site php.ini .user.ini error_log logo.png robots.txt)

fail() { printf 'ABORTADO: %s\n' "$*" >&2; exit 1; }
[[ $(id -un) == veneza ]] || fail 'usuario nao e veneza'
[[ -d $release && ! -L $release && -d $public && ! -L $public && -d $old && ! -L $old ]] || fail 'diretorios de publicacao ausentes'
[[ -f $release/site.zip && ! -L $release/site.zip ]] || fail 'site.zip ausente'
[[ $(sha256sum -- "$release/site.zip" | cut -d' ' -f1) == "$expected_zip" ]] || fail 'site.zip alterado'
[[ -f $public/.htaccess && ! -L $public/.htaccess ]] || fail '.htaccess publico ausente'
[[ $(sha256sum -- "$public/.htaccess" | cut -d' ' -f1) == "$expected_htaccess" ]] || fail '.htaccess publico mudou'
[[ ! -e $public/index.html && ! -L $public/index.html ]] || fail 'index.html publico ja existe'
[[ ! -e $old/htaccess-before-site && ! -L $old/htaccess-before-site ]] || fail 'corte anterior detectado'
for item in "${new[@]}"; do
    [[ ! -e $public/$item && ! -L $public/$item ]] || fail "destino novo ja existe: $item"
done
for item in "${legacy[@]}"; do
    [[ ! -e $old/$item && ! -L $old/$item ]] || fail "legado ja isolado: $item"
done

# Fresh private extraction from the SHA-256 checked package, not mutable old staging.
stage=$(mktemp -d "$release/site-now.XXXXXX")
unzip -q "$release/site.zip" -d "$stage"
[[ $(find "$stage" -type f | wc -l) -eq 40 ]] || fail 'pacote extraido incompleto'
[[ -z $(find "$stage" -type l -print -quit) ]] || fail 'link inesperado no pacote'
[[ -f $stage/index.html && -f $stage/assets/css/site.css && -f $stage/article.php ]] || fail 'arquivos centrais ausentes'
for item in "${new[@]}"; do
    [[ -e $stage/$item && ! -L $stage/$item ]] || fail "arquivo ausente no pacote: $item"
done

printf '%s\n' \
    'Options -Indexes' \
    'DirectoryIndex index.html index.php' \
    'SetEnv VENEZA_PUBLIC_API_ORIGIN https://api.venezapiscinas.com.br' \
    'RewriteEngine On' \
    'RewriteCond %{HTTP_HOST} ^(?:www\.)?venezapiscinas\.com\.br$ [NC]' \
    'RewriteCond %{HTTPS} !=on' \
    'RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L,NE]' \
    'RewriteCond %{HTTP_HOST} ^(?:www\.)?venezapiscinas\.com\.br$ [NC]' \
    'RewriteRule ^admin/?$ https://pena.venezapiscinas.com.br/ [R=302,L,QSD]' \
    'RewriteCond %{HTTP_HOST} ^(?:www\.)?venezapiscinas\.com\.br$ [NC]' \
    'RewriteRule ^conhecimento\.html$ /conhecimento/ [R=302,L,QSD]' \
    'RewriteCond %{HTTP_HOST} ^(?:www\.)?venezapiscinas\.com\.br$ [NC]' \
    'RewriteRule ^artigo\.html$ article.php [L,QSA]' \
    'RewriteCond %{HTTP_HOST} ^(?:www\.)?venezapiscinas\.com\.br$ [NC]' \
    'RewriteRule ^conhecimento/([a-z0-9]+(?:-[a-z0-9]+)*)/?$ article.php?slug=$1 [L,QSD]' \
    > "$stage/.htaccess.deploy-now"
chmod 644 -- "$stage/.htaccess.deploy-now"
find "$stage" -type d -exec chmod 755 -- {} +
find "$stage" -type f -exec chmod 644 -- {} +

live=0
rollback_partial() {
    local item
    if (( live == 0 )); then
        set +e
        if [[ -e $old/htaccess-before-site ]]; then
            if [[ -e $public/.htaccess ]]; then
                mv -- "$public/.htaccess" "$stage/.htaccess.failed"
            fi
            mv -- "$old/htaccess-before-site" "$public/.htaccess"
        fi
        if [[ -e $public/index.html && ! -e $stage/index.html ]]; then
            mv -- "$public/index.html" "$stage/index.html"
        fi
        for item in "${new[@]}"; do
            if [[ -e $public/$item && ! -e $stage/$item ]]; then
                mv -- "$public/$item" "$stage/$item"
            fi
        done
        printf '%s\n' 'Corte interrompido: arquivos novos removidos da area publica.' >&2
    fi
}
on_exit() {
    local status=$1
    if (( status != 0 && live == 0 )); then rollback_partial; fi
}
trap 'on_exit $?' EXIT

for item in "${new[@]}"; do mv -- "$stage/$item" "$public/$item"; done
mv -- "$stage/index.html" "$public/index.html"
mv -- "$public/.htaccess" "$old/htaccess-before-site"
mv -- "$stage/.htaccess.deploy-now" "$public/.htaccess"
[[ -f $public/index.html && -f $public/assets/css/site.css && -f $public/.htaccess ]] || fail 'site incompleto depois do corte'
live=1
trap - EXIT
printf '%s\n' 'SITE_PUBLICADO: index.html, paginas e regras ativos.'

# Quarantine exact legacy entries; preserve loja, PENA/API, imagens, video and the database.
for item in "${legacy[@]}"; do
    if [[ -e $public/$item || -L $public/$item ]]; then
        mv -- "$public/$item" "$old/$item"
        printf 'Legado isolado: %s\n' "$item"
    fi
done
printf '%s\n' 'CORTE_CONCLUIDO: loja, midias, PENA/API e banco intocados.'
