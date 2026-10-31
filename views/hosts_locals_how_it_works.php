<?php /** /hosts-locals/how-it-works @var ?array $me */ ?>
<div class="wrap hl-sec">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('hosts-locals')) ?>">Hosts &amp; Locals</a> / How it works</p>
  <article class="hl-prose">
    <h1 class="hl-h2" style="font-size:clamp(1.8rem,4vw,2.5rem)">How Hosts &amp; Locals works</h1>
    <p>RuinMyTrip started as a place for honest travel reviews. Hosts &amp; Locals adds the part a review cannot give you: the people. It runs on one free account, and you can be a traveler, a local and a host at the same time.</p>

    <h2>One profile, three roles</h2>
    <ul>
      <li><b>Traveler.</b> Everybody. Post a trip or a buddy post with a city and dates, and find people going at the same time.</li>
      <li><b>Local.</b> You live somewhere and are happy to meet travelers. You choose what you offer, from a coffee to a neighbourhood walk, and roughly when you are free. Being a local never means offering a bed.</li>
      <li><b>Host.</b> You would offer a traveler a free room or couch. You apply, a moderator reviews it, and you can pause it any time. Stay requests are not open yet.</li>
    </ul>

    <h2>Meeting a local, step by step</h2>
    <ol>
      <li>Search a city on the <a href="<?= e(url('hosts-locals')) ?>">Hosts &amp; Locals page</a>, then filter by language, interest or what a local offers.</li>
      <li>Press <b>Ask to meet</b>. The request carries no message, so it cannot be used to send anything unpleasant.</li>
      <li>The local says yes or no. A no is never announced, and nobody is asked to explain.</li>
      <li>After a yes, private messages open between the two of you. Agree on somewhere public for the first meeting.</li>
      <li>A day after you connect, either of you can leave a reference about how it went.</li>
    </ol>

    <h2>Finding a travel buddy</h2>
    <p>The travel buddy search covers dated trips, buddy posts and locals together. Search by city or country and by dates, filter by interests and travel style, and ask to join. Trips show the city and dates you chose to publish, never a hotel, a flight or a live location. <a href="<?= e(url('buddies')) ?>">Search travel buddies</a>.</p>

    <h2>Hosting, and why stays are not open yet</h2>
    <p>Hosting will always be free: no payments, no commission and no paid listings. Stay requests open once there is a proper request flow, where the host accepts or declines every guest, and a stay-specific way to report a problem and get help. Until then you can write a host profile, have it reviewed, and appear as "interested in hosting" so travelers know you are there. We do not check identity documents, so read profiles and references before you agree to anything.</p>
    <p>A host profile shows a city and, if you choose, a neighbourhood. It never holds an address, so it can never show one.</p>

    <h2>References</h2>
    <p>References are what members say about each other. They can only be written after a real connection here, a local who accepted a request or somebody you traveled with, and only once that connection is a day old. Nobody can write one for themselves, and each person can write one per member for each kind of connection. If a reference about you is unfair or untrue, report it and a moderator will review it. References are separate from place and city reviews.</p>

    <h2>Messages</h2>
    <p>Nobody can message you until you have both said yes. Your email address and phone number are never shown. You can block anybody from their profile or a conversation, and report any message.</p>

    <p class="hl-callout">Read the <a href="<?= e(url('hosts-locals/safety')) ?>">safety guide</a> before you meet anybody. No check is a guarantee of safety.</p>
    <p><a class="btn btn-primary" href="<?= e(url($me ? 'hosts-locals/me' : 'register?return=%2Fhosts-locals%2Fme')) ?>"><?= $me ? 'Set up your roles' : 'Join free' ?></a></p>
  </article>
</div>
