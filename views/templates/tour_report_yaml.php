<?php
  $posts = array();
?>
---
id: <?php $this->print_yaml($this->tour->id) ?>
area:
  id: <?php $this->print_yaml($this->tour->area->id) ?>
  name: <?php $this->print_yaml_block($this->tour->area->name, '>-', 2) ?>
  point1: [<?php echo $this->tour->area->coordinate1->lat . ', ' . $this->tour->area->coordinate1->lon ?>]
  point2: [<?php echo $this->tour->area->coordinate2->lat . ', ' . $this->tour->area->coordinate2->lon ?>]
name: <?php $this->print_yaml_block($this->tour->name, '>-', 1) ?>
author: <?php $this->print_yaml_block($this->tour->get_author_name(), '>-', 1) ?>
intro: <?php $this->print_yaml_block($this->tour->intro, '|', 1) ?>
type: <?php $this->print_yaml($this->tour->type) ?>
walkLength: <?php $this->print_yaml($this->tour->walk_length) ?>
duration: <?php $this->print_yaml($this->tour->duration) ?>
tagWhen: <?php echo $this->print_yaml($this->tour->get_tag_when_formatted()) ?>
tagWhat: <?php $this->print_yaml_block($this->tour->tag_what, '>-', 1) ?>
tagWhere: <?php $this->print_yaml_block($this->tour->tag_where, '>-', 1) ?>
accessibility: <?php $this->print_yaml_block($this->tour->accessibility, '>-', 1) ?>
track: [<?php foreach ($this->tour->coordinates as $coord): ?>
<?php echo "[$coord->lat, $coord->lon], " ?>
<?php endforeach ?>
]
mapstops:
<?php foreach ($this->tour->mapstops as $mapstop): ?>
- id: <?php $this->print_yaml($mapstop->id) ?>
  name: <?php $this->print_yaml_block($mapstop->name, '>-', 2) ?>
  description: <?php $this->print_yaml_block($mapstop->description, '|', 2) ?>
  place:
    id: <?php $this->print_yaml($mapstop->place->id) ?>
    name: <?php $this->print_yaml_block($mapstop->place->name, '>-', 3) ?>
    lat: <?php $this->print_yaml($mapstop->place->coordinate->lat) ?>
    lon: <?php $this->print_yaml($mapstop->place->coordinate->lon) ?>
  pages:
<?php for($i = 0; $i < count($mapstop->post_ids); $i++): ?>
<?php
  $page_post = get_post($mapstop->post_ids[$i]);
  // collect posts for later determination of lexicon articles
  array_push($posts, $page_post);
?>
<?php $media = $this->get_post_media($page_post) ?>
    - id: <?php $this->print_yaml($page_post->ID) ?>
      pos: <?php $this->print_yaml(($i + 1)) ?>
      guid: <?php $this->print_yaml($page_post->guid) ?>
      content: <?php $this->print_post_to_yaml($page_post, 4) ?>
<?php if(count($media) > 0): ?>
      media:
<?php foreach ($media as $mediaitem): ?>
        - guid: <?php $this->print_yaml($mediaitem->guid) ?>
<?php endforeach // mediaitem ?>
<?php endif // media exist ?>
<?php endfor // page_posts ?>
<?php endforeach // mapstops ?>
scenes:
<?php foreach ($this->tour->scenes as $scene): ?>
- id: <?php $this->print_yaml($scene->id) ?>
  name: <?php $this->print_yaml_block($scene->name, '>-', 2) ?>
  title: <?php $this->print_yaml_block($scene->title, '>-', 2) ?>
  description: <?php $this->print_yaml_block($mapstop->description, '|', 2) ?>
  excerpt: <?php $this->print_yaml_block($mapstop->excerpt, '|', 2) ?>
  src: <?php $this->print_yaml_block($scene->src, '>-', 2) ?>
  mapstops:
<?php foreach ($scene->mapstops as $mapstop): ?>
  - id: <?php $this->print_yaml($mapstop->id) ?>
<?php endforeach; // mapstops ?>
  coordinates:
<?php foreach ($scene->coordinates as $mapstopId => $coordinate): ?>
  - id: <?php $this->print_yaml($coordinate->id) ?>
    x: <?php $this->print_yaml($coordinate->lat) ?>
    y: <?php $this->print_yaml($coordinate->lon) ?>
    mapstop:
      id: <?php $this->print_yaml($mapstopId) ?>
<?php endforeach; // coorindates ?>
<?php endforeach; // scenes ?>
createdAt: <?php $this->print_yaml($this->datetime_format($this->tour->created_at)) ?>
<?php
  $lexicon_posts = $this->get_linked_lexicon_posts($posts, true);
  if(count($lexicon_posts) > 0):
?>
lexiconEntries:
<?php foreach ($lexicon_posts as $lex_post): ?>
  - id: <?php $this->print_yaml($lex_post->ID) ?>
    title: <?php $this->print_lexicon_post_title($lex_post, 3) ?>
    content: <?php $this->print_post_to_yaml($lex_post, 3) ?>
<?php endforeach ?>
<?php endif // lexicon posts are linked ?>
...