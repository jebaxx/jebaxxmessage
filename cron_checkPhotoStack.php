< ? p h p 
 
 r e q u i r e _ o n c e ( _ _ D I R _ _ . " / v e n d o r / a u t o l o a d . p h p " ) ; 
 r e q u i r e _ o n c e ( " g o o g l e / a p p e n g i n e / a p i / c l o u d _ s t o r a g e / C l o u d S t o r a g e T o o l s . p h p " ) ; 
 u s e   g o o g l e \ a p p e n g i n e \ a p i \ c l o u d _ s t o r a g e \ C l o u d S t o r a g e T o o l s ; 
 
 u s e   \ L I N E \ L I N E B o t \ H T T P C l i e n t \ C u r l H T T P C l i e n t ; 
 u s e   \ L I N E \ L I N E B o t ; 
 u s e   \ L I N E \ L I N E B o t \ R e s p o n s e ; 
 u s e   \ L I N E \ L I N E B o t \ M e s s a g e B u i l d e r \ T e x t M e s s a g e B u i l d e r ; 
 u s e   \ L I N E \ L I N E B o t \ C o n s t a n t \ H T T P H e a d e r ; 
 
 r e q u i r e _ o n c e ( " g o o g l e / a p p e n g i n e / a p i / c l o u d _ s t o r a g e / C l o u d S t o r a g e T o o l s . p h p " ) ; 
 u s e   g o o g l e \ a p p e n g i n e \ a p i \ c l o u d _ s t o r a g e \ C l o u d S t o r a g e T o o l s ; 
 
 $ g s _ f i l e   =   " g s : / / j e b a x x c o n n e c t o r . a p p s p o t . c o m / p h o t o _ q u e u e / " ; 
 
 $ r e s u l t   =   g l o b ( $ g s _ f i l e   .   " * . 0 0 5 " ) ; 
 
 f o r e a c h   $ r e s u l t   a s   $ f i l e n a m e   { 
 
         i f   ( p r e g _ m a t c h ( " @ ( [ ^ / _ ] + ) _ [ 0 - 9 ] + \ . 0 0 5 @ " ,   $ f i l e n a m e ,   $ m a t c h e d )   = =   F A L S E )   { 
 	 s y s l o g ( L O G _ W A R N I N G ,   " u n e x p e c t e d   f i l e   n a m e   f o u n d " ) ; 
 	 c o n t i n u e ; 
         } 
 
         P u s h M e s s a g e ( $ m a t c h e d [ 1 ] ,   " 愥Ok01YWeW0_0󤕘񍲄L0媺d0K0c0_0�0. . . " .   $ f i l e n a m e ) ; 
 } 
 
 
 f u n c t i o n   P u s h M e s s a g e ( $ L i n e _ i d ,   $ m e s s a g e )   { 
 
         / /   c r e a t e   H T T P C l i e n t   i n s t a n c e 
         $ h t t p C l i e n t   =   n e w   C u r l H T T P C l i e n t ( A C C E S S _ T O K E N ) ; 
         $ B o t   =   n e w   L I N E B o t ( $ h t t p C l i e n t ,   [ ' c h a n n e l S e c r e t '   = >   S E C R E T _ T O K E N ] ) ; 
 
         $ p u s h M e s s a g e B u i l d e r   =   n e w   T e x t M e s s a g e B u i l d e r ( $ m e s s a g e ) ; 
         $ r e s p o n s e   =   $ B o t - > p u s h M e s s a g e ( $ l i n e I d ,   $ p u s h M e s s a g e B u i l d e r ) ; 
 
         i f   ( $ r e s p o n s e - > g e t H T T P S t a t u s ( )   ! =   2 0 0 )   { 
 	 s y s l o g ( L O G _ E R R ,   " F a i l e d   t o   s e n d i n g   a   p u s h   m e s s a g e   a n d   s t a t u s   c o d e   i s   " .   $ r e s p o n s e - > g e t H T T P S t a t u s ( ) ) ; 
         } 
 } 
 
 ? > 
 