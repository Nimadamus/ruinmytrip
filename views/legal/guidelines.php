<div class="wrap prose" style="padding:30px 20px 60px">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / Community Guidelines</p>
  <h1>Community Guidelines</h1>
  <p style="font-size:1.05rem">
    Keep it honest, keep it useful, keep it safe. That is most of it. The rest of this page exists
    so you know exactly where the lines are before you write something, rather than afterwards.
  </p>

  <?php /* The reassurance goes FIRST, above the prohibitions. A traveler deciding whether to write
           what a hotel was really like needs to know we will not delete it for being unflattering,
           and that answer being buried under a list of bans is how a review site ends up with
           nothing but praise on it. */ ?>
  <h2>A bad review is a good review</h2>
  <ul>
    <li><b>Negative reviews are welcome.</b> The disappointing parts are the useful parts. If it was
      overpriced, filthy, or nothing like the photographs, say so.</li>
    <li><b>Strong criticism is allowed.</b> "I would not go back and here is why" is exactly what
      this site is for.</li>
    <li><b>We do not remove a review for being critical.</b> Nothing in our moderation reads a
      rating. A one-star review and a five-star review are held to the same rules and no others.</li>
    <li><b>Reporting is not deleting.</b> A report tells a person to look. It does not hide
      anything, and no number of reports hides anything: there is no threshold, because a
      review that enough people dislike is not the same as a review that breaks a rule.</li>
    <li><b>Nobody can pay to change any of this.</b> A business cannot buy a rating, a position, or
      the removal of a review. We have not built a way, and we are not going to.</li>
    <li><b>Disagreeing is fine.</b> Two travelers can have opposite experiences of the same hotel
      and both be telling the truth.</li>
  </ul>

  <h2>What we do remove</h2>
  <p class="muted" style="margin:0 0 8px">None of these is about the opinion. All of them are about the conduct.</p>
  <ul>
    <li><b>Made-up experiences.</b> Reviewing somewhere you did not go, whatever you say about it.</li>
    <li><b>Undisclosed conflicts of interest.</b> Reviewing your own business, a competitor, your
      employer, or anywhere you were paid or comped to write about without saying so.</li>
    <li><b>Spam and promotion.</b> Advertising, affiliate links, repetition, and posting the same
      thing across many places.</li>
    <li><b>Harassment, hate speech, threats and discrimination.</b> Aimed at anyone: another
      traveler, a member of staff, or an owner.</li>
    <li><b>Somebody's private information.</b> Names of staff who are not public figures, home
      addresses, phone numbers, photographs of people who did not agree to be photographed, or
      anyone's precise or real-time location.</li>
    <li><b>Content that is not about the place.</b> Political arguments, personal disputes, and
      complaints about a delivery company that has nothing to do with the restaurant.</li>
    <li><b>Sexual, adult or hookup content.</b></li>
    <li><b>Anything illegal or unsafe, and anything involving minors.</b></li>
  </ul>

  <h2 id="travel-partners">Travel partners, check-ins and city rooms</h2>
  <ul>
    <li><b>Say yes before you talk.</b> Nobody can message you about a trip until you accept them, and you can decline without the other person being told.</li>
    <li><b>A city room is for travelers who are in that city today.</b> Keep it to plans, questions and company. No selling, no recruiting, no moving people to another app to pitch them.</li>
    <li><b>Never ask another traveler for money,</b> a deposit, a loan or a card number, whatever the story is.</li>
    <li><b>Do not share where somebody else is staying,</b> their full name, or their plans, and think before you post your own.</li>
    <li><b>No pressure.</b> Somebody who says no, stops replying or checks out has answered.</li>
  </ul>

<?php if (function_exists('rmt_trust_mode') && rmt_trust_mode() !== 'off'): ?>
  <h2 id="vouches">Vouches, flags and restrictions</h2>
  <ul>
    <li><b>After you have met.</b> Once a trip you shared is over, or you were checked in to the same city and both wrote in its room, each of you can leave the other one vouch or one flag. It is private: the other person never sees who answered or what was written.</li>
    <li><b>Vouch for people you would travel with again. Flag people who made you feel unsafe, lied about who they are, or asked for money.</b> A flag is not for a disagreement about where to eat.</li>
    <li><b>Your numbers are yours.</b> Your score and counts are shown to you and to our moderators, never on your profile, and nothing on the site is ranked by them.</li>
    <li><b>Flags never restrict an account by themselves.</b> When several travelers flag the same member, a moderator reads the flags, checks whether they come from one group or one argument, and decides.</li>
    <li><b>A restriction pauses messaging and nothing else.</b> You are told the same minute with the reason, your profile and posts stay where they are, and you can appeal on your trust page. Every decision is recorded.</li>
    <li><b>Flagging people together to get somebody restricted is itself a breach,</b> and we look for it.</li>
  </ul>
<?php endif; ?>

  <h2>How enforcement works</h2>
  <p>
    A report goes to a person. They read the content itself, not just the report, and decide. A
    review can be hidden or removed, and either way it is recorded: what happened, who
    decided, and what state it was in before. Content is not deleted outright, so a decision can be
    reversed. Repeated or serious breaches can mean a warning, a suspension or a ban, and anything
    involving safety is escalated immediately. See <a href="<?= e(url('safety')) ?>">Meetup Safety</a>.
  </p>

  <h2>If you think we got it wrong</h2>
  <p>
    Tell us through <a href="<?= e(url('contact')) ?>">contact</a>. If a place page has wrong
    information rather than a review problem, use the correction link on the place page itself.
  </p>
</div>
