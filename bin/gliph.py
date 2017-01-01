import base64
import json
import StringIO

import requests


def trim_dict(o):
    for k in o:
        if hasattr(o[k], '__len__') and len(o[k]) > 128:
            o[k] = '<...%s chars omitted...>' % (len(o[k]))
        elif isinstance(o[k], dict):
            trim_dict(o[k])


class GliphSessionException(Exception):
    def __init__(self, error):
        self.error = error


class GliphSession(object):
    def __init__(self, url=None, debug=False, client='Leibniz Session',
                 with_exceptions=False):
        self.url = url or 'https://gli.ph/api/v2'
        self.debug = debug
        self.client = client
        self.with_exceptions = with_exceptions
        self.user_id = None
        self.auth_id = None
        self.auth_key = None
        self.s = requests.Session()

    @property
    def token(self):
        return '%s.%s.%s' % (self.user_id, self.auth_id, self.auth_key)

    def _request(self, action, endpoint, data, with_auth):
        url = '%s/%s' % (self.url, endpoint)
        data['action'] = action
        headers = {'User-Agent': self.client}
        if with_auth:
            headers['X-Gliph-Token'] = self.token

        r = self.s.post(url, data=json.dumps(data), headers=headers)
        if self.debug:
            print "Request"
            print "=" * 80
            print "POST " + url
            for k in headers:
                print "%s: %s" % (k, headers[k])
            trim_dict(data)
            print json.dumps(data, indent=4)
            print "\nResponse"
            print "=" * 80
            print json.dumps(r.json(), indent=4)

        try:
            r = r.json()
        except:
            raise GliphSessionException('unknown')

        if not r['success'] and self.with_exceptions:
            raise GliphSessionException(r['error'])

        return r

    # ACCOUNT

    # account
    def signup(self, gliph=None, email=None, passphrase=None, username=None):
        params = {'passphrase': passphrase}
        if username:
            params['username'] = username
        if gliph:
            params['gliph'] = gliph
        if email:
            params['email'] = email

        r = self._request('create', 'account', params, with_auth=False)

        if r['success']:
            self.user_id = r['user_id']
            self.auth_id = r['auth_id']
            self.auth_key = r['auth_key']
        return r['success']

    def delete_account(self, passphrase):
        params = {'confirmation': {'passphrase': passphrase}}

        r = self._request('delete', 'account', params, with_auth=True)

        if r['success']:
            self.user_id = None
            self.auth_id = None
            self.auth_key = None
        return r['success']

    # account/available
    def go_wild(self):
        r = self._request('create', 'account/available', {}, with_auth=False)
        return r['gliph']

    def is_gliph_available(self, gliph):
        params = {'gliph': gliph}
        r = self._request('read', 'account/available', params,
                          with_auth=False)
        return r['is_available']

    def is_email_available(self, email):
        params = {'email': email}
        r = self._request('read', 'account/available', params,
                          with_auth=False)
        return r['is_available']

    # account/auth
    def login(self, gliph=None, email=None, username=None, passphrase=None):
        params = {'passphrase': passphrase}
        if gliph:
            params['gliph'] = gliph
        elif email:
            params['email'] = email
        else:
            params['username'] = username

        r = self._request('create', 'account/auth', params, with_auth=False)

        if r['success']:
            self.user_id = r['user_id']
            self.auth_id = r['auth_id']
            self.auth_key = r['auth_key']
            return True
        else:
            return False

    def account_authorizations(self, paginate=None):
        if paginate:
            params = {'paginate': paginate}
        else:
            params = {}
        r = self._request('read', 'account/auth', params, with_auth=True)
        return r['paginate'], r['authorizations']

    def change_password(self, old_passphrase, new_passphrase):
        params = {'passphrase': new_passphrase,
                  'confirmation': {'passphrase': old_passphrase}}
        r = self._request('update', 'account/auth', params, with_auth=True)
        return r['success']

    def logout(self, here_only=False):
        params = {}
        if here_only:
            params['ids'] = [self.auth_id, ]

        r = self._request('delete', 'account/auth', params, with_auth=True)
        return r['success']

    # account/reset
    def send_reset(self, gliph=None, email=None):
        if gliph:
            params = {'gliph': gliph}
        else:
            params = {'email': email}

        r = self._request('create', 'account/reset', params, with_auth=False)
        return r['success']

    def reset_password(self, reset_code, passphrase):
        params = {'reset_code': reset_code, 'passphrase': passphrase}
        r = self._request('update', 'account/reset', params, with_auth=False)
        if r['success']:
            self.user_id = r['user_id']
            self.auth_id = r['auth_id']
            self.auth_key = r['auth_key']
            return True
        else:
            return False

    # account/settings
    def account_settings(self):
        r = self._request('read', 'account/settings', {}, with_auth=True)
        return r['settings']

    def update_setting(self, name, value, passphrase=None):
        return self.update_settings([{'id': name, 'value': value}],
                                    passphrase=passphrase)

    def update_settings(self, updates, passphrase=None):
        params = {'objects': updates}
        if passphrase:
            params['confirmation'] = {'passphrase': passphrase}

        r = self._request('update', 'account/settings', params,
                          with_auth=True)
        return r['success']

    # FACETS

    # facets
    def create_facet(self, facet_type, content, public=False,
                     discoverable=False, account_facet=False,
                     passphrase=None):
        params = {'facet_type': facet_type,
                  'meta_data': {'public': public,
                                'discoverable': discoverable,
                                'account_facet': account_facet},
                  'content': content}
        if passphrase:
            params['confirmation'] = {'passphrase': passphrase}

        r = self._request('create', 'facets', params, with_auth=True)
        if r['success']:
            return r['facet']
        else:
            return False

    def create_facet_simple(self, facet_type, content, **kw):
        content = {'content_type': 'text/plain', 'content': content}
        return self.create_facet(facet_type, content, **kw)

    def facets(self):
        r = self._request('read', 'facets', {}, with_auth=True)
        return r['facets']

    def update_facet(self, facet_id, content=None, public=None,
                     discoverable=None, account_facet=None,
                     passphrase=None):
        params = {'id': facet_id, 'meta_data': {}}
        if content is not None:
            params['content'] = content

        if public is not None:
            params['meta_data']['public'] = public
        if discoverable is not None:
            params['meta_data']['discoverable'] = discoverable
        if account_facet is not None:
            params['meta_data']['account_facet'] = account_facet

        if not params['meta_data']:
            del params['meta_data']

        return self.update_facets([params], passphrase=passphrase)

    def update_facets(self, facets, passphrase=None):
        params = {'objects': facets}
        if passphrase:
            params['confirmation'] = {'passphrase': passphrase}
        r = self._request('update', 'facets', params, with_auth=True)
        return r['success']

    def delete_facet(self, facet_id):
        return self.delete_facets([facet_id])

    def delete_facets(self, facet_ids):
        params = {'ids': facet_ids}
        r = self._request('delete', 'facets', params, with_auth=True)
        return r['success']

    # facets/validation
    def send_validation(self, facet_id):
        params = {'facet_id': facet_id}
        r = self._request('create', 'facets/validation', params,
                          with_auth=True)
        return r['success']

    # facets/available
    def is_facet_available(self, facet_type, simple_content):
        params = {'facet_type': facet_type, 'content': simple_content}
        r = self._request('read', 'facets/available', params,
                          with_auth=True)
        return r['success']

    # CLOAKS

    # cloaks
    def create_cloak(self, cloaked_email, sig, label):
        params = {'cloaked_email': cloaked_email, 'sig': sig, 'label': label}
        r = self._request('create', 'cloaks', params, with_auth=True)
        if r['success']:
            return r['cloak']
        else:
            return False

    def cloaks(self, paginate=None):
        if paginate:
            params = {'paginate': paginate}
        else:
            params = {}
        r = self._request('read', 'cloaks', params, with_auth=True)
        return r['paginate'], r['meta'], r['cloaks']

    def update_cloak(self, cloak_id, label):
        params = {'objects': [{'id': cloak_id, 'label': label}]}
        r = self._request('update', 'cloaks', params, with_auth=True)
        return r['success']

    def delete_cloak(self, cloak_id):
        return self.delete_cloaks([cloak_id])

    def delete_cloaks(self, cloak_ids):
        params = {'ids': cloak_ids}
        r = self._request('delete', 'cloaks', params, with_auth=True)
        return r['success']

    # cloaks/others
    def get_cloak_others(self, cloak_id):
        params = {'cloak_id': cloak_id}
        r = self._request('read', 'cloaks/others', params, with_auth=True)
        return r['others']

    def update_cloak_other(self, cloak_id, other_id, blocked=True):
        other = {'id': other_id, 'blocked': blocked}
        return self.update_cloak_others(cloak_id, [other])

    def update_cloak_others(self, cloak_id, others):
        params = {'cloak_id': cloak_id, 'objects': others}
        r = self._request('update', 'cloaks/others', params, with_auth=True)
        return r['success']

    # cloaks/available
    def get_cloak(self):
        r = self._request('create', 'cloaks/available', {}, with_auth=True)
        return r['email_address'], r['sig']

    # cloaks/message
    def send_cloaked_email(self, cloak_id, rcpt, subject, body, bcc,
                           file_obj=None, filename=None):
        params = {'cloak_id': cloak_id, 'rcpt': rcpt, 'subject': subject,
                  'body': body, 'bcc': bcc}
        if file_obj:
            params['content'] = base64.b64encode(file_obj.read())
            params['filename'] = filename

        r = self._request('create', 'cloaks/message', params, with_auth=True)
        return r['success']

    # GLIPHME

    # gliphme
    def create_gliphmelink(self, label):
        params = {'label': label}
        r = self._request('create', 'gliphmelinks', params, with_auth=True)
        return r['link']

    def gliphmelinks(self, paginate=None):
        if paginate:
            params = {'paginate': paginate}
        else:
            params = {}
        r = self._request('read', 'gliphmelinks', params, with_auth=True)
        return r['paginate'], r['links']

    def update_gliphmelink(self, gliphmelink_id, label=None, active=None):
        link = {'id': gliphmelink_id}
        if label is not None:
            link['label'] = label
        if active is not None:
            link['active'] = active
        return self.update_gliphmelinks([link])

    def update_gliphmelinks(self, links):
        params = {'objects': links}
        r = self._request('update', 'gliphmelinks', params, with_auth=True)
        return r['success']

    def delete_gliphmelink(self, gliphmelink_id):
        return self.delete_gliphmelinks([gliphmelink_id])

    def delete_gliphmelinks(self, gliphmelink_ids):
        params = {'ids': gliphmelink_ids}
        r = self._request('delete', 'gliphmelinks', params, with_auth=True)
        return r['success']

    # STORE

    # store/products
    def products(self):
        r = self._request('read', 'store/products', {}, with_auth=True)
        return r['stores']

    # store/payments
    def buy_from_appstore(self, receipt, transaction_id):
        params = {'provider': 'appstore', 'receipt': receipt,
                  'transaction_id': transaction_id}
        r = self._request('create', 'store/payments', params, with_auth=True)
        return r['id']

    def buy_from_stripe(self, products, cc_name, cc_address1, cc_zip,
                        cc_number, cc_cvc, cc_exp_month, cc_exp_year,
                        cc_address2=None):
        cc_info = {'name': cc_name, 'address_line1': cc_address1,
                   'address_zip': cc_zip, 'number': cc_number, 'cvc': cc_cvc,
                   'exp_month': cc_exp_month, 'exp_year': cc_exp_year}
        if cc_address2:
            cc_info['address_line2'] = cc_address2
        params = {'provider': 'stripe', 'products': products,
                  'cc_info': cc_info}
        r = self._request('create', 'store/payments', params, with_auth=True)
        return r['id']

    def check_appstore_payment(self, external_id):
        params = {'external_id': external_id}
        r = self._request('read', 'store/payments', params, with_auth=True)
        return r['payment_status']

    def check_stripe_payment(self, payment_id):
        params = {'payment_id': payment_id}
        r = self._request('read', 'store/payments', params, with_auth=True)
        return r['payment_status']

    # MONEY (i.e. bitcoin)

    # money/wallets
    def attach_coinbase_wallet(self, code, src='native'):
        params = {'provider': 'coinbase', 'auth': {'code': code, 'src': src}}

        r = self._request('create', 'money/wallets', params, with_auth=True)

        if r['success']:
            return r['wallet']
        else:
            return False

    def attach_blockchain_wallet(self, alias, password,
                                 second_password=None):
        params = {'provider': 'blockchain', 'auth': {'alias': alias,
                                                     'password': password}}
        if second_password:
            params['auth']['second_password'] = second_password

        r = self._request('create', 'money/wallets', params, with_auth=True)

        if r['success']:
            return r['wallet']
        else:
            return False

    def wallets(self):
        r = self._request('read', 'money/wallets', {}, with_auth=True)
        return r['wallets']

    def update_coinbase_wallet(self, wallet_id, code):
        wallet = {'id': wallet_id, 'auth': {'code': code}}
        return self.update_wallets([wallet])

    def update_blockchain_wallet(self, wallet_id, password,
                                 second_password=None):
        wallet = {'id': wallet_id, 'auth': {'password': password}}
        if second_password:
            wallet['auth']['second_password'] = second_password
        return self.update_wallets([wallet])

    def update_wallets(self, wallets):
        params = {'objects': wallets}
        r = self._request('update', 'money/wallets', params, with_auth=True)
        return r['success']

    def delete_wallet(self, wallet_id, passphrase):
        self.delete_wallets([wallet_id], passphrase)

    def delete_wallets(self, wallet_ids, passphrase):
        params = {'ids': wallet_ids,
                  'confirmation': {'passphrase': passphrase}}
        r = self._request('delete', 'money/wallets', params, with_auth=True)
        return r['success']

    # money/preauthorization
    def coinbase_authorization_url(self, src='native'):
        params = {'provider': 'coinbase', 'src': src}
        r = self._request('read', 'money/preauthorization', params,
                          with_auth=True)
        return r['authorize_url']

    # money/credentials
    def wallet_credentials(self, passphrase, wallet_id=None):
        params = {'confirmation': {'passphrase': passphrase}}
        if wallet_id:
            params['wallet_id'] = wallet_id

        r = self._request('read', 'money/credentials', params, with_auth=True)

        return r['credentials']

    def delete_wallet_credentials(self, passphrase, wallet_id=None):
        params = {'confirmation': {'passphrase': passphrase}}
        if wallet_id:
            params['wallet_id'] = wallet_id

        r = self._request('delete', 'money/credentials', params,
                          with_auth=True)

        return r['success']

    # money/providers
    def default_wallet_provider(self):
        r = self._request('read', 'money/providers', {}, with_auth=True)
        return r['provider']

    # money/terms
    def wallet_terms(self, provider):
        params = {'provider': provider}
        r = self._request('read', 'money/terms', params, with_auth=True)
        return r['terms']

    # money/exchange_rate
    def exchange_rate(self, currency=None):
        if currency:
            params = {'currency': currency}
        else:
            params = {}

        r = self._request('read', 'money/exchange_rate', params,
                          with_auth=True)

        return r['rate']

    # money/transactions
    def send_bitcoin(self, amount, rcpt_address, wallet_id=None, label=None,
                     fee=None):
        params = {'amount': amount, 'rcpt_address': rcpt_address}
        if wallet_id:
            params['wallet_id'] = wallet_id
        if label:
            params['label'] = label
        if fee:
            params['fee'] = fee

        r = self._request('create', 'money/transactions', params,
                          with_auth=True)

        return r['transaction']

    def transactions(self, wallet_id=None, paginate=None):
        params = {}
        if wallet_id:
            params['wallet_id'] = wallet_id
        if paginate:
            params['paginate'] = paginate

        r = self._request('read', 'money/transactions', params,
                          with_auth=True)

        if r['success']:
            return r['paginate'], r['transactions']
        else:
            return False

    # USERS

    # users
    def search_for_user_by_gliph(self, gliph):
        params = {'gliph': gliph}
        r = self._request('read', 'users', params, with_auth=True)
        if r['success']:
            return r['user'], r['connection'], r['groups']
        else:
            return False

    def search_for_user_by_gliphmelink(self, url):
        params = {'gliphmelink': url}
        r = self._request('read', 'users', params, with_auth=True)
        if r['success']:
            return r['user'], r['connection'], r['groups'], r['gliphmelink']
        else:
            return False

    def search_for_user_by_invitecode(self, invitecode):
        params = {'invitecode': invitecode}
        r = self._request('read', 'users', params, with_auth=True)
        if r['success']:
            return r['user'], r['connection'], r['groups'], r['gliphmelink']
        else:
            return False

    def search_for_user_by_user_id(self, user_id):
        params = {'user_id': user_id, 'user_only': True}
        r = self._request('read', 'users', params, with_auth=True)
        if r['success']:
            return r['user']#, r['connection'], r['groups']
            #return r['user'], r['connection'], r['groups'], r['gliphmelink']
        else:
            return False

    def block_user(self, user_id):
        params = {'user_id': user_id, 'blocked': True}
        r = self._request('update', 'users', params, with_auth=True)
        return r['success']

    def unblock_user(self, user_id):
        params = {'user_id': user_id, 'blocked': False}
        r = self._request('update', 'users', params, with_auth=True)
        return r['success']

    # CONNECTIONS

    # connections
    def create_connection(self, gliph=None, gliphmelink=None, user_id=None,
                          invitecode=None, label=None, initial_msg=None,
                          facet_ids=None, group=False, group_name=None,
                          group_open=False):
        params = {'users': [{}], 'group': group}
        if gliph:
            params['users'][0]['gliph'] = gliph
        elif gliphmelink:
            params['users'][0]['gliphmelink'] = gliphmelink
        elif user_id:
            params['users'][0]['user_id'] = user_id
        elif invitecode:
            params['users'][0]['invitecode'] = invitecode

        if label is not None:
            params['label'] = label
        if initial_msg is not None:
            params['initial_msg'] = initial_msg
        if facet_ids:
            params['facets'] = facet_ids

        if group:
            params['group_meta'] = {'name': group_name, 'open': group_open}

        r = self._request('create', 'connections', params, with_auth=True)

        if r['success']:
            return r['connection']
        else:
            return False

    # TODO add/remove members from a group, group settings
    # (open or not, group name)

    def connections(self, ids=None, paginate=None):
        params = {}
        if ids is not None:
            params['ids'] = ids
        elif paginate:
            params['paginate'] = paginate

        r = self._request('read', 'connections', params, with_auth=True)

        if r['success']:
            return r['paginate'], r['connections']
        else:
            return False

    def update_connection(self, conn_id, label=None, remove_label=False,
                          push_content=None, push_label=None,
                          send_emails=None, send_push=None, facet_ids=None):
        conn = {'id': conn_id, 'settings': {}}

        if label is not None:
            conn['label'] = label
        if remove_label:
            conn['label'] = None

        if facet_ids is not None:
            conn['facets'] = facet_ids

        if push_content is not None:
            conn['settings']['push_content'] = push_content
        if push_label is not None:
            conn['settings']['push_label'] = push_label
        if send_emails is not None:
            conn['settings']['send_emails'] = send_emails
        if send_push is not None:
            conn['settings']['send_push'] = send_push
        if not conn['settings']:
            del conn['settings']

        return self.update_connections([conn])

    def update_connections(self, conns):
        params = {'objects': conns}
        r = self._request('update', 'connections', params, with_auth=True)
        return r['success']

    def delete_connection(self, connection_id):
        self.delete_connections([connection_id])

    def delete_connections(self, connection_ids):
        params = {'ids': connection_ids}
        r = self._request('delete', 'connections', params, with_auth=True)
        return r['success']

    # LOCATIONS

    # locations

    LOCATIONS = {
        'sf': {'lat': 37.77493, 'long': -122.41942},
    }

    def search_for_locations(self, text=None, location=None):
        params = {}
        if text is not None:
            params['search'] = text
        if location is not None:
            params['near'] = location
        r = self._request('read', 'locations', params, with_auth=True)
        return r['locations']

    # LISTINGS

    # listings
    def _build_images(self, n=1):
        images = []
        for i in xrange(n):
            url = 'http://lorempixel.com/600/600/cats/fullsize photo No.' + \
                ' %s/' % i
            r = requests.get(url)
            content_id, key = self.upload_media_put(r.content)
            thumb_part = {'content_type': 'image/png',
                          'content_id': content_id, 'key': key,
                          'rel': 'thumbnail'}

            url = 'http://lorempixel.com/240/240/cats/thumbnail photo No.' + \
                ' %s/' % i
            r = requests.get(url)
            content_id, key = self.upload_media_put(r.content)
            full_part = {'content_type': 'image/png',
                         'content_id': content_id, 'key': key,
                         'rel': 'fullsize'}

            images.append({'content_type': 'multipart/alternative',
                           'content': [thumb_part, full_part]})
        return images

    def _build_listing_params(self, title=None, description=None, state=None,
                              images=None, category_id=None, currency_id=None,
                              amount=None, location_id=None, accept_cash=None,
                              accept_bitcoin=None, bitcoin_discount=0,
                              delivery_by_meetup=None):
        params = {}
        if title is not None:
            params['title'] = title
        if description is not None:
            params['description'] = description
        if state is not None:
            params['state'] = state
        if images is not None:
            params['images'] = images

        if category_id is not None:
            params['category'] = category_id
        if location_id is not None:
            params['location'] = location_id

        if currency_id is not None or amount is not None:
            params['price'] = {}
            if currency_id is not None:
                params['price']['currency'] = currency_id
            if amount is not None:
                params['price']['amount'] = amount

        if accept_cash is not None or accept_bitcoin is not None:
            params['payment_methods'] = []
            if accept_cash is not None:
                params['payment_methods'].append({'method': 'cash'})
            if accept_bitcoin is not None:
                params['payment_methods'].append({
                    'method': 'bitcoin', 'discount': bitcoin_discount})

        if delivery_by_meetup is not None:
            params['delivery_methods'] = []
            params['delivery_methods'].append({'method': 'meetup'})

        return params

    def create_listing(self, **kwargs):
        params = self._build_listing_params(**kwargs)
        r = self._request('create', 'listings', params, with_auth=True)
        return r['listing']

    def update_listing(self, listing_id, version=None, **kwargs):
        params = self._build_listing_params(**kwargs)

        params['id'] = listing_id
        if version is not None:
            params['version'] = version
        full_params = {'objects': [params]}

        r = self._request('update', 'listings', full_params, with_auth=True)
        return r['success']

    def _read_listings(self, params, paginate=None):
        if paginate is not None:
            params['paginate'] = paginate
        r = self._request('read', 'listings', params, with_auth=True)
        return r['paginate'], r['listings']

    def listing_drafts(self, paginate=None):
        return self._read_listings({'state': 'draft'}, paginate)

    def listing_actives(self, paginate=None):
        return self._read_listings({'state': 'open'}, paginate)

    def listing_archived(self, paginate=None):
        return self._read_listings({'state': 'closed'}, paginate)

    def listings_for_user(self, user_id, paginate=None):
        return self._read_listings({'user_id': user_id}, paginate)

    # listings/responses
    def _read_listing_responses(self, params, paginate=None):
        if paginate is not None:
            params['paginate'] = paginate
        r = self._request('read', 'listings/responses', params, with_auth=True)
        return r['paginate'], r['listing_responses']

    def listing_responses_for_my_listing(self, listing_id):
        return self._read_listing_responses({'listing_id': listing_id})

    def my_active_listing_responses(self):
        return self._read_listing_responses({'state': 'open'})

    def my_archived_listing_responses(self):
        return self._read_listing_responses({'state': 'closed'})

    def reload_listing_response(self, listing_response_id):
        r = self._request('read', 'listings/responses', {
            'ids': [listing_response_id]}, with_auth=True)
        return r['listing_responses'][0]

    # buyer
    def listing_response_make_offer(self, listing_response_id, amount, method):
        r = self._request('update', 'listings/responses', {'objects': [{
            'id': listing_response_id,
            'state': 'offer_on_table',
            'offer': {'amount': amount, 'method': method}
            }]}, with_auth=True)
        return r['success']

    # buyer
    def listing_response_take_back_offer(self, listing_response_id):
        r = self._request('update', 'listings/responses', {'objects': [{
            'id': listing_response_id,
            'state': 'discussion',
            }]}, with_auth=True)
        return r['success']

    # seller
    def listing_response_reject_offer(self, listing_response_id):
        r = self._request('update', 'listings/responses', {'objects': [{
            'id': listing_response_id,
            'state': 'discussion',
            }]}, with_auth=True)
        return r['success']

    # seller
    def listing_response_accept_offer(self, listing_response_id):
        r = self._request('update', 'listings/responses', {'objects': [{
            'id': listing_response_id,
            'state': 'awaiting_payment',
            }]}, with_auth=True)
        return r['success']

    # buyer or seller
    def listing_response_back_out_of_accepted_offer(self, listing_response_id):
        r = self._request('update', 'listings/responses', {'objects': [{
            'id': listing_response_id,
            'state': 'discussion',
            }]}, with_auth=True)
        return r['success']

    # buyer
    def listing_response_make_bitcoin_payment_FAKE(self, listing_response_id):
        r = self._request('update', 'listings/responses', {'objects': [{
            'id': listing_response_id,
            'state': 'awaiting_review',
            }]}, with_auth=True)
        return r['success']

    # seller
    def listing_response_confirm_payment(self, listing_response_id):
        r = self._request('update', 'listings/responses', {'objects': [{
            'id': listing_response_id,
            'state': 'awaiting_review',
            }]}, with_auth=True)
        return r['success']

    # buyer or seller
    def listing_response_leave_deal(self, listing_response_id):
        r = self._request('update', 'listings/responses', {'objects': [{
            'id': listing_response_id,
            'state': 'failed_left',
            }]}, with_auth=True)
        return r['success']

    # listings/categories
    def listings_categories(self):
        r = self._request('read', 'listings/categories', {}, with_auth=True)
        return r['categories']

    # listings/currencies
    def listings_currencies(self):
        r = self._request('read', 'listings/currencies', {}, with_auth=True)
        return r['currencies']

    # REPORTS

    # reports
    def report_object(self, object_name, object_id):
        r = self._request(
            'create', 'reports',
            {'object_name': object_name, 'object_id': object_id},
            with_auth=True)
        return r['report']

    # MESSAGES

    # messages
    def messages(self, connection_id, limit=None, delayed=False,
                 paginate=None, mark_as_read=True):
        params = {'connection_id': connection_id,
                  'mark_as_read': mark_as_read}
        if paginate:
            params['paginate'] = paginate
            if limit:
                params['paginate']['limit'] = limit
        elif limit:
            params['paginate'] = {'limit': limit}
        if delayed:
            params['delayed'] = delayed

        r = self._request('read', 'messages', params, with_auth=True)

        if r['success']:
            return r['paginate'], r['messages']
        else:
            return False

    def delete_message(self, connection_id, message_id):
        self.delete_messages(connection_id, [message_id])

    def delete_messages(self, connection_id, message_ids=None):
        params = {'connection_id': connection_id}
        if message_ids is not None:
            params['ids'] = message_ids
        r = self._request('delete', 'messages', params, with_auth=True)
        return r['success']

    def send_message(self, connection_id, text=None, image=None,
                     bitcoin=None, expire=None, delay=None):
        params = {'connection_id': connection_id}
        if expire is not None:
            params['expire'] = expire
        if delay is not None:
            params['delay'] = delay
        params['content'] = self._build_message(text, image, bitcoin)

        r = self._request('create', 'messages', params, with_auth=True)

        if r['success']:
            return r['message']
        else:
            return False

    def _build_message(self, text=None, image=None, bitcoin=None):
        content = {'content_type': 'multipart/related', 'content': []}
        if text is not None:
            text_part = {'content_type': 'text/plain', 'content': text}
            content['content'].append(text_part)

        if image is not None:
            if 'thumb' in image:
                content_id, key = self.upload_media_put(image['thumb'])
                thumb_part = {'content_type': 'image/png',
                              'content_id': content_id, 'key': key,
                              'rel': 'thumbnail'}
            content_id, key = self.upload_media_put(image['full'])
            full_part = {'content_type': 'image/png',
                         'content_id': content_id, 'key': key,
                         'rel': 'fullsize'}
            if 'thumb' in image:
                image_part = {'content_type': 'multipart/alternative',
                              'content': [thumb_part, full_part]}
            else:
                image_part = full_part
            content['content'].append(image_part)

        if bitcoin is not None:
            bitcoin_part = {'content_type': 'x-gliph/bitcoin',
                            'content': {'amount': bitcoin}}
            content['content'].append(bitcoin_part)

        if len(content['content']) == 0:
            print "At least one part is required"
            raise Exception
        elif len(content['content']) == 1:
            content = content['content'][0]

        return content

    # INVITATIONS

    # invitations
    def _build_invitation(self, email=None, sms=None, msg=None):
        invitation = {}
        if email is not None:
            invitation['email'] = email
        elif sms is not None:
            invitation['sms'] = sms
        else:
            raise Exception('Email or SMS must be specified')
        if msg is not None:
            invitation['msg'] = msg
        return invitation

    def get_multiuse_invite(self, qty=1):
        invitations = [{'multiuse': True} for i in xrange(qty)]
        return self.send_invites(invitations)

    def get_group_multiuse_invite(self, connection_id):
        invitations = [{'multiuse': True, 'connection_id': connection_id}]
        return self.send_invites(invitations)

    def send_invites(self, invitations):
        if type(invitations) is not list:
            invitations = [invitations]

        params = {'invitations': invitations}
        r = self._request('create', 'invitations', params, with_auth=True)

        if r['success']:
            return r['invitations']
        else:
            return False

    def get_group_invitation_details(self, code, key):
        params = {'code': code, 'key': key}
        r = self._request('read', 'invitations/responses', params,
                          with_auth=False)
        if r['success']:
            return r['connection']
        else:
            return False

    def join_group_from_invitation(self, code, key):
        params = {'code': code, 'key': key}
        r = self._request('create', 'invitations/responses', params,
                          with_auth=True)
        if r['success']:
            return r['connection']
        else:
            return False

    # SUBSCRIPTIONS

    # subscriptions
    def subscribe(self, sub_type, reg_id):
        params = {'sub_type': sub_type, 'registration_id': reg_id}
        r = self._request('create', 'subscriptions', params, with_auth=True)

        return r['success']

    def unsubscribe(self, reg_ids=None):
        params = {}
        if reg_ids is not None:
            params['registration_ids'] = reg_ids

        r = self._request('delete', 'subscriptions', params, with_auth=True)

        return r['success']

    # MEDIA

    # media
    def upload_media_post(self, file_obj):
        url = '%s/media' % (self.url, )
        headers = {'User-Agent': 'Leibniz Session',
                   'X-Gliph-Token': self.token}

        r = self.s.post(url, files={'media': file_obj}, headers=headers)

        if self.debug:
            print "Request"
            print "=" * 80
            print "POST " + url
            for k in headers:
                print "%s: %s" % (k, headers[k])
            print "<multipart/form-data>"
            print "Response"
            print "=" * 80
            print json.dumps(r.json(), indent=4)

        return r.json()['content_id'], r.json()['key']

    def upload_media_put(self, file_obj):
        if isinstance(file_obj, basestring):
            file_obj = StringIO.StringIO(file_obj)
        url = '%s/media' % (self.url, )
        headers = {'User-Agent': 'Leibniz Session',
                   'X-Gliph-Token': self.token}

        r = self.s.put(url, data=file_obj, headers=headers)

        if self.debug:
            print "Request"
            print "=" * 80
            print "PUT " + url
            for k in headers:
                print "%s: %s" % (k, headers[k])
            print "<binary data>"
            print "Response"
            print "=" * 80
            print json.dumps(r.json(), indent=4)

        return r.json()['content_id'], r.json()['key']

    def download_media_get(self, content_id, key):
        return self.download_media(content_id, key, 'get')

    def download_media_post(self, content_id, key):
        return self.download_media(content_id, key, 'post')

    def download_media(self, content_id, key, method):
        url = '%s/media/%s' % (self.url, content_id)
        headers = {'User-Agent': 'Leibniz Session',
                   'X-Gliph-Token': self.token,
                   'X-Gliph-Key': key}
        data = {}
        if method == 'get':
            r = self.s.get(url, params=data, headers=headers)
        elif method == 'post':
            r = self.s.post(url, data=json.dumps(data), headers=headers)
        else:
            raise Exception('Unsupported method')

        if self.debug:
            print "Request"
            print "=" * 80
            if method == 'get':
                print "GET " + r.url
            elif method == 'post':
                print "POST " + r.url
            for k in headers:
                print "%s: %s" % (k, headers[k])
            trim_dict(data)
            print json.dumps(data, indent=4)
            print "Response"
            print "=" * 80
            if r.status_code == 200:
                print "<binary output>"
            else:
                print json.dumps(r.json(), indent=4)

        return r.content


